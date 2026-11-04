<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator']);
$role = $u['role']; $admin = in_array($role, ['super_admin', 'district_admin'], true);

function my_mission(array $u, int $id): ?array {
    [$sc, $sp] = mission_scope($u);
    return row("SELECT m.*, d.district_id, d.title AS dtitle FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE m.id=?$sc", array_merge([$id], $sp));
}

if (is_post()) {
    csrf_check(); $a = post('action');
    if ($a === 'create' && $admin) {
        $dis = row('SELECT * FROM disasters WHERE id=?', [(int)post('disaster_id')]); $qty = (int)post('target_qty'); $title = post('title'); $item = post('item_type');
        if (!$dis || $dis['status'] === 'resolved') flash('error', 'Choose an active or monitored disaster.');
        elseif ($role === 'district_admin' && (int)$dis['district_id'] !== (int)$u['district_id']) flash('error', 'You can only create missions in your own district.');
        elseif ($title === '' || mb_strlen($title) > 160) flash('error', 'Enter a mission title.');
        elseif (!in_array($item, ITEM_TYPES, true)) flash('error', 'Choose an item type.');
        elseif ($qty < 1) flash('error', 'Target quantity must be at least 1.');
        else {
            $pri = in_array(post('priority'), PRIORITIES, true) ? post('priority') : 'medium';
            q("INSERT INTO relief_missions (disaster_id,title,item_type,unit,target_qty,budget,status,priority,created_by) VALUES (?,?,?,?,?,?,'planned',?,?)", [(int)$dis['id'], $title, $item, post('unit', 'units') ?: 'units', $qty, max(0, (float)post('budget')), $pri, (int)$u['id']]);
            $id = (int)db()->lastInsertId(); q('UPDATE relief_missions SET code=? WHERE id=?', [mission_code($id), $id]); audit('mission.create', 'mission', $id, $title);
            flash('success', 'Bulk mission created. Assign an NGO team and coordinator next.');
        }
    } elseif ($a === 'assign' && $admin) {
        $m = my_mission($u, (int)post('id')); $ngo = (int)post('ngo_id') ?: null; $co = (int)post('coordinator_id') ?: null;
        if (!$m) flash('error', 'Mission not found in your scope.');
        elseif (!in_array($m['status'], ['planned', 'assigned'], true)) flash('error', 'Only missions that have not been accepted can be reassigned.');
        elseif ($ngo && !val("SELECT COUNT(*) FROM users WHERE id=? AND role='ngo' AND status='active'", [$ngo])) flash('error', 'Pick an active NGO / Volunteer team.');
        elseif ($co && !val("SELECT COUNT(*) FROM users WHERE id=? AND role='coordinator' AND status='active' AND district_id=?", [$co, (int)$m['district_id']])) flash('error', 'The coordinator must be active and belong to the disaster district.');
        else {
            q('UPDATE relief_missions SET ngo_id=?, coordinator_id=?, status=? WHERE id=?', [$ngo, $co, $ngo ? 'assigned' : 'planned', (int)$m['id']]); audit('mission.assign', 'mission', (int)$m['id'], $m['title']);
            if ($ngo && $ngo !== (int)$m['ngo_id']) notify_user($ngo, 'New mission assignment', ($m['code'] ?: '') . ' ' . $m['title'], 'missions.php', 'mission');
            if ($co && $co !== (int)$m['coordinator_id']) notify_user($co, 'Mission assigned to your zone', $m['title'], 'missions.php', 'mission');
            flash('success', 'Assignment saved.');
        }
    } elseif (in_array($a, ['complete', 'cancel'], true) && $admin) {
        $m = my_mission($u, (int)post('id'));
        if (!$m) flash('error', 'Mission not found in your scope.'); elseif (in_array($m['status'], ['completed', 'cancelled'], true)) flash('error', 'This mission is already closed.');
        else { close_mission((int)$m['id'], $a === 'complete' ? 'completed' : 'cancelled', 'Closed by ' . ROLES[$role][0]); audit('mission.' . $a, 'mission', (int)$m['id'], $m['title']); flash('success', $a === 'complete' ? 'Mission marked completed.' : 'Mission cancelled and its resources released.'); }
    } elseif ($a === 'accept_bulk' && $role === 'ngo') {
        $m = my_mission($u, (int)post('id'));
        if (!$m || $m['status'] !== 'assigned' || $m['demand_id']) flash('error', 'This mission cannot be accepted here.');
        else { q("UPDATE relief_missions SET status='accepted' WHERE id=?", [(int)$m['id']]); mission_event((int)$m['id'], 'accepted', 'Mission accepted'); audit('mission.accept', 'mission', (int)$m['id'], $m['title']); flash('success', 'Mission accepted. You can dispatch it now.'); }
    } elseif ($a === 'decline' && $role === 'ngo') {
        $m = my_mission($u, (int)post('id'));
        if (!$m || $m['status'] !== 'assigned') flash('error', 'Only newly assigned missions can be declined.');
        else {
            if ($m['demand_id']) close_mission((int)$m['id'], 'cancelled', 'Declined by NGO team'); else q("UPDATE relief_missions SET status='planned', ngo_id=NULL WHERE id=?", [(int)$m['id']]);
            audit('mission.decline', 'mission', (int)$m['id'], $m['title']); notify_where("role='district_admin' AND district_id=?", [(int)$m['district_id']], 'Mission declined', ($u['organization'] ?: 'An NGO') . ' declined ' . $m['title'], 'demands.php', 'mission'); flash('success', 'Mission declined. The administration has been told.');
        }
    }
    redirect('dashboard/missions.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

[$msc, $mpc] = mission_scope($u);
$st = $_GET['status'] ?? 'open'; $w = $msc; $p = $mpc;
if ($st === 'open') $w .= " AND m.status IN ('planned','assigned','accepted','in_transit','arrived')"; elseif (in_array($st, ['assigned', 'accepted', 'in_transit', 'arrived', 'completed', 'cancelled'], true)) { $w .= ' AND m.status=?'; $p[] = $st; } else $st = 'all';
$list = rows("SELECT m.*, d.title dtitle, d.district_id, di.name district, z.name zone, n.organization ngo, c.first_name cf, c.last_name cl, v.plate, t.name driver FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id JOIN districts di ON di.id=d.district_id LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN users c ON c.id=m.coordinator_id LEFT JOIN vehicles v ON v.id=m.vehicle_id LEFT JOIN team_members t ON t.id=m.driver_id WHERE 1=1$w ORDER BY m.status IN ('completed','cancelled'), FIELD(m.priority,'critical','high','medium','low'), m.created_at DESC", $p);
$tot = row("SELECT COUNT(*) n, COALESCE(SUM(m.target_qty),0) t, COALESCE(SUM(m.delivered_qty),0) dv, COALESCE(SUM(m.status IN ('in_transit','arrived')),0) mv, COALESCE(SUM(m.status='assigned'),0) wait FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE m.status<>'cancelled'$msc", $mpc);
if ($admin) {
    [$ds, $dp] = district_scope($u, 'd.district_id');
    $disasters = rows("SELECT d.id, d.title, di.name FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status<>'resolved'$ds ORDER BY d.declared_at DESC", $dp);
    $ngos = rows("SELECT id, first_name, organization FROM users WHERE role='ngo' AND status='active' ORDER BY organization");
    $coords = rows("SELECT id, first_name, last_name, district_id FROM users WHERE role='coordinator' AND status='active' ORDER BY first_name");
}
$titles = ['super_admin' => 'Relief Monitoring', 'district_admin' => 'Relief Dispatch', 'ngo' => 'My Missions', 'coordinator' => 'Active Missions'];
dash_head($titles[$role], 'missions', $admin ? 'Monitor dispatch, assign teams and track verified delivery' : ($role === 'ngo' ? 'Missions assigned to your team' : 'Missions heading to your zone'));
?>
<div class="kpis"><?= kpi('', 'Missions', (int)$tot['n'], 'In scope', 'zap', 'b') . kpi('blue', 'On the road', (int)$tot['mv'], 'In transit or arrived', 'truck', 'b') . kpi('green', 'Verified delivered', (int)$tot['dv'], 'of ' . number_format($tot['t']) . ' target units', 'check', 'g') . ($role === 'ngo' ? kpi('amber', 'Awaiting your reply', (int)$tot['wait'], 'Accept or decline', 'clock', 'a') : kpi('blue', 'Overall progress', pct($tot['dv'], $tot['t']) . '%', 'Verified vs target', 'chart', 'b')) ?></div>
<?php if ($admin): ?><details class="panel"><summary>Create a bulk relief mission</summary>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="two"><div class="field"><label for="disaster_id">Disaster</label><select id="disaster_id" name="disaster_id" required><option value="">Select disaster</option><?php foreach ($disasters as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name'] . ' · ' . $d['title']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="title">Mission title</label><input type="text" id="title" name="title" maxlength="160" required></div></div>
    <div class="three" style="grid-template-columns:repeat(4,1fr)"><div class="field"><label for="item_type">Item type</label><select id="item_type" name="item_type"><?= opts(ITEM_TYPES) ?></select></div><div class="field"><label for="target_qty">Target quantity</label><input type="number" id="target_qty" name="target_qty" min="1" required></div><div class="field"><label for="unit">Unit</label><input type="text" id="unit" name="unit" value="packs" maxlength="30"></div><div class="field"><label for="priority">Priority</label><select id="priority" name="priority"><?= opts(PRIORITIES, 'medium') ?></select></div></div>
    <div class="field" style="max-width:320px"><label for="budget">Budget (BDT)</label><input type="number" id="budget" name="budget" min="0" value="0"></div>
    <p class="hint" style="margin-bottom:1rem">Bulk missions cover a whole disaster. For single-zone needs, coordinators submit demands and teams accept them.</p><button class="btn btn-primary" type="submit">Create mission</button></form></details><?php endif;
tabs(['open' => 'Open', 'in_transit' => 'In transit', 'arrived' => 'Arrived', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'], $st); ?>
<section class="panel"><table class="tbl"><thead><tr><th>Mission</th><th>Progress (verified)</th><th>Team</th><th>Status</th><th style="text-align:right"><?= $admin ? 'Manage' : 'Actions' ?></th></tr></thead><tbody>
<?php foreach ($list as $m): $pc = pct($m['delivered_qty'], $m['target_qty']); $open = !in_array($m['status'], ['completed', 'cancelled'], true); ?>
  <tr><td><code><?= e($m['code']) ?></code> <?= badge($m['priority']) ?><br><b><?= e($m['title']) ?></b><small><?= e($m['district']) ?><?= $m['zone'] ? ' · ' . e($m['zone']) : '' ?> · <?= e(ucfirst($m['item_type'])) ?></small></td>
    <td class="prog"><div class="bar"><i style="width:<?= $pc ?>%"></i></div><small><?= number_format($m['delivered_qty']) ?> / <?= number_format($m['target_qty']) ?> <?= e($m['unit']) ?> (<?= $pc ?>%)</small></td>
    <td><?= $m['ngo'] ? e($m['ngo']) : '<small>No NGO yet</small>' ?><small>Coordinator: <?= $m['cf'] ? e($m['cf'] . ' ' . $m['cl']) : 'not assigned' ?><?= $m['plate'] ? ' · ' . e($m['plate']) : '' ?></small></td>
    <td><?= badge($m['status']) ?><?php if (in_array($m['status'], ['in_transit', 'arrived'], true)): ?><small>Progress <?= (int)$m['progress'] ?>%<?= $m['eta'] ? ' · ETA ' . e(date('H:i', strtotime($m['eta']))) : '' ?></small><?php endif; ?></td>
    <td><div class="actions" style="flex-wrap:wrap">
      <?php if (in_array($m['status'], ['in_transit', 'arrived'], true)): ?><a class="btn btn-outline btn-xs" href="<?= url('dashboard/tracking.php?id=' . (int)$m['id']) ?>">Track</a><?php endif; ?>
      <?php if ($role === 'ngo' && $m['status'] === 'assigned'): ?>
        <?php if ($m['demand_id']): ?><a class="btn btn-primary btn-xs" href="<?= url('dashboard/available.php?accept=' . (int)$m['demand_id']) ?>">Review &amp; accept</a><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept_bulk"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-primary btn-xs">Accept</button></form><?php endif; ?>
        <form method="post" data-confirm="Decline this mission?"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline btn-xs" style="color:var(--red-d)">Decline</button></form><?php endif; ?>
      <?php if ($role === 'ngo' && $m['status'] === 'accepted'): ?><a class="btn btn-primary btn-xs" href="<?= url('dashboard/dispatch.php?mission=' . (int)$m['id']) ?>">Dispatch</a><?php endif; ?>
      <?php if ($role === 'ngo' && in_array($m['status'], ['in_transit', 'arrived'], true)): ?><a class="btn btn-green btn-xs" href="<?= url('dashboard/confirm.php') ?>">Confirm delivery</a><?php endif; ?>
      <?php if ($role === 'coordinator' && in_array($m['status'], ['in_transit', 'arrived'], true)): ?><a class="btn btn-green btn-xs" href="<?= url('dashboard/confirm.php') ?>">Confirm</a><?php endif; ?>
    </div>
    <?php if ($admin && $open): ?>
      <?php if (in_array($m['status'], ['planned', 'assigned'], true)): ?><form method="post" style="margin-bottom:.35rem;justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <select name="ngo_id" aria-label="NGO team" style="max-width:140px"><option value="">NGO team…</option><?php foreach ($ngos as $n): ?><option value="<?= (int)$n['id'] ?>" <?= (int)$m['ngo_id'] === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['organization'] ?: $n['first_name']) ?></option><?php endforeach; ?></select>
        <select name="coordinator_id" aria-label="Coordinator" style="max-width:120px"><option value="">Coordinator…</option><?php foreach ($coords as $c) if ((int)$c['district_id'] === (int)$m['district_id']): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$m['coordinator_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option><?php endif; ?></select><button class="btn btn-outline btn-xs">Assign</button></form><?php endif; ?>
      <form method="post" style="justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline btn-xs" name="action" value="complete" onclick="return confirm('Mark this mission completed?')">Mark completed</button><button class="btn btn-outline btn-xs" name="action" value="cancel" style="color:var(--red-d)" onclick="return confirm('Cancel this mission and release its zone lock and stock?')">Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$list) echo empty_row(5, $admin ? 'No missions here yet.' : 'No missions in this view.'); ?></tbody></table></section>
<?php dash_foot();
