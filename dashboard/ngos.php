<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin']);
$isSuper = $u['role'] === 'super_admin';

if (is_post()) {
    csrf_check();
    if (post('action') === 'release') {
        $l = row('SELECT l.*, m.status mstatus, m.title, m.id mid, z.district_id, n.id nid FROM zone_locks l JOIN relief_missions m ON m.id=l.mission_id JOIN zones z ON z.id=l.zone_id JOIN users n ON n.id=l.ngo_id WHERE l.id=? AND l.active_key=1', [(int)post('id')]);
        if (!$l || (!$isSuper && (int)$l['district_id'] !== (int)$u['district_id'])) flash('error', 'Lock not found in your scope.');
        elseif (!in_array($l['mstatus'], ['accepted', 'assigned'], true)) flash('error', 'This mission is already on the road. Ask the NGO team to finish or cancel it.');
        else {
            close_mission((int)$l['mid'], 'cancelled', 'Released by ' . ROLES[$u['role']][0]);
            audit('zone.release', 'mission', (int)$l['mid'], $l['title']);
            notify_user((int)$l['nid'], 'Zone lock released', $l['title'] . ' was released by the administration and the demand was reopened.', 'missions.php', 'mission');
            flash('success', 'Lock released. The demand is open again for matching.');
        }
    }
    redirect('dashboard/ngos.php');
}

$ds = $isSuper ? '' : ' AND z.district_id=' . (int)$u['district_id'];
$locks = rows("SELECT l.*, z.name zone, di.name district, n.organization, m.code, m.title, m.status mstatus, m.progress FROM zone_locks l JOIN zones z ON z.id=l.zone_id JOIN districts di ON di.id=z.district_id JOIN users n ON n.id=l.ngo_id JOIN relief_missions m ON m.id=l.mission_id WHERE l.active_key=1$ds ORDER BY l.locked_at DESC");
$ngos = rows("SELECT n.*,
 (SELECT COUNT(*) FROM vehicles v WHERE v.ngo_id=n.id) vcount, (SELECT COUNT(*) FROM team_members t WHERE t.ngo_id=n.id AND t.status='active') tcount,
 (SELECT COUNT(*) FROM inventory i WHERE i.ngo_id=n.id) stock, (SELECT COUNT(*) FROM inventory i WHERE i.ngo_id=n.id AND i.quantity-i.reserved<=i.low_threshold) lowc,
 (SELECT COUNT(*) FROM relief_missions m WHERE m.ngo_id=n.id AND m.status IN ('assigned','accepted','in_transit','arrived')) act,
 (SELECT COUNT(*) FROM relief_missions m WHERE m.ngo_id=n.id AND m.status='completed') done,
 (SELECT COALESCE(SUM(dl.recipients),0) FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id WHERE m.ngo_id=n.id AND dl.status='verified') ppl
 FROM users n WHERE n.role='ngo' ORDER BY n.status='pending' DESC, n.organization");
if (!$isSuper) {
    $mine = array_column(rows('SELECT DISTINCT m.ngo_id FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE d.district_id=? AND m.ngo_id IS NOT NULL', [(int)$u['district_id']]), 'ngo_id');
    $ngos = array_values(array_filter($ngos, fn($n) => in_array($n['id'], $mine) && $n['status'] === 'active'));
}
dash_head($isSuper ? 'NGO / Volunteer Management' : 'NGO Coordination', 'ngos', $isSuper ? 'Every registered response team' : 'Teams working in ' . $u['district_name'] . ' and the zones they hold');
?>
<div class="kpis"><?= kpi('', 'Teams', count($ngos), $isSuper ? 'Registered' : 'Active in your district', 'users', 'b') . kpi('green', 'Active missions', array_sum(array_column($ngos, 'act')), 'Assigned or moving', 'zap', 'g') . kpi('blue', 'Zones locked', count($locks), 'One team per zone and item', 'lock', 'b') . kpi('red', 'Low-stock teams', count(array_filter($ngos, fn($n) => $n['lowc'] > 0)), 'At least one item low', 'box', 'r') ?></div>
<section class="panel"><header><div><h2>Zone locks</h2><span class="sub">Prevents two teams from delivering the same item to the same zone</span></div></header>
<table class="tbl"><thead><tr><th>Zone</th><th>Item</th><th>Held by</th><th>Mission</th><th>Status</th><th>Locked</th><th style="text-align:right">Admin override</th></tr></thead><tbody>
<?php foreach ($locks as $l): ?><tr><td><b><?= e($l['zone']) ?></b><small><?= e($l['district']) ?></small></td><td><?= e(ucfirst($l['item_type'])) ?></td><td><?= e($l['organization']) ?></td><td><code><?= e($l['code']) ?></code></td><td><?= badge($l['mstatus']) ?><small><?= (int)$l['progress'] ?>%</small></td><td><small style="color:var(--ink)"><?= ago($l['locked_at']) ?></small></td>
  <td><div class="actions"><?php if (in_array($l['mstatus'], ['accepted', 'assigned'], true)): ?><form method="post" data-confirm="Release this lock and reopen the demand?"><?= csrf_field() ?><input type="hidden" name="action" value="release"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-outline btn-xs" style="color:var(--red-d)">Release &amp; reopen</button></form><?php else: ?><small>On the road</small><?php endif; ?></div></td></tr>
<?php endforeach; if (!$locks) echo empty_row(7, 'No active zone locks.'); ?></tbody></table></section>
<section class="panel"><header><h2>Teams</h2><?php if ($isSuper): ?><a href="<?= url('dashboard/users.php?status=pending') ?>" style="font-size:13px">Approve registrations →</a><?php endif; ?></header>
<table class="tbl"><thead><tr><th>Organisation</th><th>Contact</th><th>Status</th><th class="num">Vehicles</th><th class="num">Team</th><th class="num">Stock lines</th><th class="num">Active</th><th class="num">Completed</th><th class="num">People reached</th></tr></thead><tbody>
<?php foreach ($ngos as $n): ?><tr><td><b><?= e($n['organization'] ?: '—') ?></b></td><td><?= e($n['first_name'] . ' ' . $n['last_name']) ?><small><?= e($n['phone']) ?></small></td><td><?= badge($n['status']) ?></td><td class="num"><?= (int)$n['vcount'] ?></td><td class="num"><?= (int)$n['tcount'] ?></td><td class="num"><?= (int)$n['stock'] ?><?= $n['lowc'] ? ' <span class="badge b-red">' . (int)$n['lowc'] . ' low</span>' : '' ?></td><td class="num"><?= (int)$n['act'] ?></td><td class="num"><?= (int)$n['done'] ?></td><td class="num"><?= number_format($n['ppl']) ?></td></tr>
<?php endforeach; if (!$ngos) echo empty_row(9, 'No teams to show.'); ?></tbody></table></section>
<?php dash_foot();
