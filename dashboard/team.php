<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['ngo']); $id = (int)$u['id'];
if (is_post()) {
    csrf_check(); $a = post('action');
    if ($a === 'add') {
        $name = post('name'); $phone = preg_replace('/[\s-]/', '', post('phone'));
        if ($name === '' || mb_strlen($name) > 100) flash('error', 'Enter the member name.');
        elseif ($phone !== '' && !preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $phone)) flash('error', 'Enter a valid mobile number or leave it blank.');
        else { q('INSERT INTO team_members (ngo_id,name,title,phone,is_driver) VALUES (?,?,?,?,?)', [$id, $name, post('title') ?: 'Field worker', $phone, isset($_POST['is_driver']) ? 1 : 0]); audit('team.add', 'team', (int)db()->lastInsertId(), $name); flash('success', 'Team member added.'); }
    } else {
        $t = row('SELECT * FROM team_members WHERE id=? AND ngo_id=?', [(int)post('id'), $id]);
        $busy = $t ? (int)val("SELECT COUNT(*) FROM relief_missions m LEFT JOIN mission_members mm ON mm.mission_id=m.id WHERE m.status IN ('accepted','in_transit','arrived') AND (m.driver_id=? OR mm.member_id=?)", [(int)$t['id'], (int)$t['id']]) : 0;
        if (!$t) flash('error', 'Team member not found.');
        elseif ($a === 'toggle') { if ($t['status'] === 'active' && $busy) flash('error', 'This person is on an active mission.'); else { q('UPDATE team_members SET status=? WHERE id=?', [$t['status'] === 'active' ? 'inactive' : 'active', (int)$t['id']]); flash('success', 'Updated.'); } }
        elseif ($a === 'delete') { if ($busy) flash('error', 'This person is on an active mission. Deactivate them later instead.'); else { q('DELETE FROM mission_members WHERE member_id=?', [(int)$t['id']]); q('UPDATE relief_missions SET driver_id=NULL WHERE driver_id=?', [(int)$t['id']]); q('DELETE FROM team_members WHERE id=?', [(int)$t['id']]); audit('team.remove', 'team', (int)$t['id'], $t['name']); flash('success', 'Member removed.'); } }
    }
    redirect('dashboard/team.php');
}
$list = rows("SELECT t.*, (SELECT COUNT(*) FROM relief_missions m LEFT JOIN mission_members mm ON mm.mission_id=m.id WHERE m.status IN ('accepted','in_transit','arrived') AND (m.driver_id=t.id OR mm.member_id=t.id)) busy FROM team_members t WHERE t.ngo_id=? ORDER BY t.status, t.name", [$id]);
dash_head('Team Management', 'team', $u['organization']);
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('', 'Members', count($list), 'Registered', 'users', 'b') . kpi('green', 'Drivers', count(array_filter($list, fn($t) => $t['is_driver'] && $t['status'] === 'active')), 'Active', 'truck', 'g') . kpi('amber', 'On a mission', count(array_filter($list, fn($t) => $t['busy'])), 'Right now', 'zap', 'a') ?></div>
<details class="panel"><summary>Add a team member</summary><form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="three"><div class="field"><label for="name">Name</label><input type="text" id="name" name="name" required maxlength="100"></div><div class="field"><label for="title">Role</label><input type="text" id="title" name="title" placeholder="e.g. Field worker, Medical volunteer" maxlength="60"></div><div class="field"><label for="phone">Mobile (optional)</label><input type="tel" id="phone" name="phone" placeholder="01XXXXXXXXX"></div></div>
  <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="is_driver"> This person can drive for missions</label><button class="btn btn-primary">Add member</button></form></details>
<section class="panel"><table class="tbl"><thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
<?php foreach ($list as $t): ?><tr><td><b><?= e($t['name']) ?></b><?= $t['is_driver'] ? ' <span class="chip">Driver</span>' : '' ?></td><td><?= e($t['title']) ?></td><td><?= e($t['phone'] ?: '—') ?></td><td><?= $t['busy'] ? '<span class="badge b-blue">On mission</span>' : ($t['status'] === 'active' ? '<span class="badge b-green">Available</span>' : '<span class="badge b-gray">Inactive</span>') ?></td>
  <td><div class="actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-outline btn-xs" name="action" value="toggle"><?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button><button class="btn btn-outline btn-xs" name="action" value="delete" style="color:var(--red-d)" onclick="return confirm('Remove this member?')">Remove</button></form></div></td></tr>
<?php endforeach; if (!$list) echo empty_row(5, 'Add the people who go out on missions.'); ?></tbody></table></section>
<?php dash_foot();
