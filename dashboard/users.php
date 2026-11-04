<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin']);

if (is_post()) {
    csrf_check();
    $a = post('action'); $id = (int)post('id'); $t = row('SELECT * FROM users WHERE id=?', [$id]);
    $name = $t ? $t['first_name'] . ' ' . $t['last_name'] : '';
    if (!$t) flash('error', 'User not found.');
    elseif ($id === (int)$u['id']) flash('error', 'You cannot change your own account here.');
    elseif ($a === 'approve' && $t['status'] === 'pending') { q("UPDATE users SET status='active' WHERE id=?", [$id]); audit('user.approve', 'user', $id, $name . ' · ' . ROLES[$t['role']][0]); notify_user($id, 'Account approved', 'You can now use your dashboard.', 'index.php', 'account'); flash('success', "$name approved."); }
    elseif ($a === 'reject' && $t['status'] === 'pending') { q('DELETE FROM users WHERE id=?', [$id]); audit('user.reject', 'user', $id, $name . ' · ' . $t['email']); flash('success', "Registration from $name removed."); }
    elseif ($a === 'suspend' && $t['status'] === 'active') { q("UPDATE users SET status='suspended' WHERE id=?", [$id]); audit('user.suspend', 'user', $id, $name); flash('success', "$name suspended."); }
    elseif ($a === 'reactivate' && $t['status'] === 'suspended') { q("UPDATE users SET status='active' WHERE id=?", [$id]); audit('user.reactivate', 'user', $id, $name); flash('success', "$name reactivated."); }
    elseif ($a === 'edit') {
        $role = post('role'); $dist = (int)post('district_id') ?: null; $zone = (int)post('zone_id') ?: null;
        if (!isset(ROLES[$role])) flash('error', 'Choose a role.');
        elseif (in_array($role, ['district_admin', 'coordinator'], true) && !$dist) flash('error', 'District Admins and Coordinators need a district.');
        elseif ($zone && !val('SELECT COUNT(*) FROM zones WHERE id=? AND district_id=?', [$zone, (int)$dist])) flash('error', 'That zone is not in the selected district.');
        elseif (post('first_name') === '' || post('last_name') === '') flash('error', 'Enter first and last name.');
        else {
            q('UPDATE users SET first_name=?, last_name=?, role=?, district_id=?, zone_id=?, organization=? WHERE id=?', [post('first_name'), post('last_name'), $role, $dist, $role === 'coordinator' ? $zone : null, post('organization') ?: null, $id]);
            audit('user.edit', 'user', $id, $name . ' → ' . ROLES[$role][0] . ($zone ? ' · zone ' . $zone : '')); notify_user($id, 'Your account was updated', 'Role: ' . ROLES[$role][0], 'profile.php', 'account'); flash('success', 'User updated.');
        }
    } else flash('error', 'That action does not apply to this account.');
    redirect('dashboard/users.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$st = $_GET['status'] ?? 'all'; $w = ''; $p = [];
if (in_array($st, ['pending', 'active', 'suspended'], true)) { $w = ' WHERE u.status=?'; $p[] = $st; } else $st = 'all';
$list = rows("SELECT u.*, d.name AS district, z.name AS zone FROM users u LEFT JOIN districts d ON d.id=u.district_id LEFT JOIN zones z ON z.id=u.zone_id$w ORDER BY u.status='pending' DESC, u.created_at DESC", $p);
$cnt = ['pending' => 0, 'active' => 0, 'suspended' => 0]; foreach (rows('SELECT status, COUNT(*) c FROM users GROUP BY status') as $r) $cnt[$r['status']] = (int)$r['c'];
$edit = ($e = (int)($_GET['edit'] ?? 0)) ? row('SELECT * FROM users WHERE id=?', [$e]) : null;
$zopts = ''; foreach (rows('SELECT z.id, z.name, d.name dn FROM zones z JOIN districts d ON d.id=z.district_id ORDER BY d.name, z.name') as $z) $zopts .= '<option value="' . (int)$z['id'] . '"' . ($edit && (int)$edit['zone_id'] === (int)$z['id'] ? ' selected' : '') . '>' . e($z['dn'] . ' · ' . $z['name']) . '</option>';
dash_head('User & Role Management', 'users', 'Approve registrations, assign roles, districts and zones');
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('amber', 'Waiting for approval', $cnt['pending'], 'New registrations', 'clock', 'a') . kpi('green', 'Active accounts', $cnt['active'], 'Can sign in', 'user', 'g') . kpi('red', 'Suspended', $cnt['suspended'], 'Blocked', 'lock', 'r') ?></div>
<?php if ($edit && (int)$edit['id'] !== (int)$u['id']): ?><section class="panel"><header><h2>Edit <?= e($edit['first_name'] . ' ' . $edit['last_name']) ?></h2><a href="<?= url('dashboard/users.php') ?>" style="font-size:13px">Close</a></header>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <div class="two"><div class="field"><label for="fn">First name</label><input type="text" id="fn" name="first_name" value="<?= e($edit['first_name']) ?>" required></div><div class="field"><label for="ln">Last name</label><input type="text" id="ln" name="last_name" value="<?= e($edit['last_name']) ?>" required></div></div>
    <div class="three"><div class="field"><label for="role">Role</label><select id="role" name="role"><?php foreach (ROLES as $k => $r): ?><option value="<?= $k ?>" <?= $edit['role'] === $k ? 'selected' : '' ?>><?= e($r[0]) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="district_id">District</label><select id="district_id" name="district_id"><?= district_options((int)$edit['district_id']) ?></select></div>
      <div class="field"><label for="zone_id">Zone (coordinators)</label><select id="zone_id" name="zone_id"><option value="">No zone</option><?= $zopts ?></select></div></div>
    <div class="field" style="max-width:480px"><label for="org">Organisation / office</label><input type="text" id="org" name="organization" value="<?= e($edit['organization']) ?>"></div>
    <button class="btn btn-primary">Save changes</button></form></section><?php endif; ?>
<?php tabs(['all' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended'], $st); ?>
<section class="panel"><table class="tbl"><thead><tr><th>Person</th><th>Role</th><th>District / Zone</th><th>Organisation</th><th>Status</th><th>Last sign-in</th><th style="text-align:right">Action</th></tr></thead><tbody>
<?php foreach ($list as $r): $me = (int)$r['id'] === (int)$u['id']; ?>
  <tr><td><b><?= e($r['first_name'] . ' ' . $r['last_name']) ?></b><small><?= e($r['email']) ?> · <?= e($r['phone']) ?></small></td><td><?= e(ROLES[$r['role']][0]) ?></td><td><?= e($r['district'] ?: '—') ?><?php if ($r['zone']): ?><small><?= e($r['zone']) ?></small><?php endif; ?></td><td><?= e($r['organization'] ?: '—') ?></td>
    <td><?= badge($r['status']) ?></td><td><small style="color:var(--ink)"><?= $r['last_login'] ? ago($r['last_login']) : 'never' ?></small></td>
    <td><div class="actions"><?php if (!$me): ?><a class="btn btn-outline btn-xs" href="?edit=<?= (int)$r['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <?php if ($r['status'] === 'pending'): ?><button class="btn btn-green btn-xs" name="action" value="approve">Approve</button><button class="btn btn-outline btn-xs" name="action" value="reject" style="color:var(--red-d)" onclick="return confirm('Remove this registration?')">Reject</button>
      <?php elseif ($r['status'] === 'active'): ?><button class="btn btn-outline btn-xs" name="action" value="suspend" style="color:var(--red-d)" onclick="return confirm('Suspend this account?')">Suspend</button>
      <?php else: ?><button class="btn btn-outline btn-xs" name="action" value="reactivate">Reactivate</button><?php endif; ?></form><?php else: ?><small>This is you</small><?php endif; ?></div></td></tr>
<?php endforeach; ?></tbody></table></section>
<?php dash_foot();
