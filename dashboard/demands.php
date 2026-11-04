<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'coordinator']);
$role = $u['role']; $admin = $role !== 'coordinator';

function demand_in_scope(array $u, int $id): ?array {
    $d = row('SELECT d.*, z.name zone, z.lat zlat, z.lng zlng FROM demands d JOIN zones z ON z.id=d.zone_id WHERE d.id=?', [$id]);
    if (!$d) return null;
    if ($u['role'] === 'district_admin' && (int)$d['district_id'] !== (int)$u['district_id']) return null;
    return $d;
}

if (is_post() && $admin) {
    csrf_check(); $a = post('action'); $d = demand_in_scope($u, (int)post('id'));
    if (!$d) flash('error', 'Demand not found in your scope.');
    elseif ($a === 'assign') {
        $ngo = (int)post('ngo_id'); $holder = zone_lock_holder((int)$d['zone_id'], $d['item_type']);
        if ($d['status'] !== 'pending') flash('error', 'Only pending demands can be matched.');
        elseif ($holder) flash('error', 'This zone is already locked for ' . $d['item_type'] . ' by ' . $holder['organization'] . '.');
        elseif (!val("SELECT COUNT(*) FROM users WHERE id=? AND role='ngo' AND status='active'", [$ngo])) flash('error', 'Pick an active NGO / Volunteer team.');
        else {
            $co = find_coordinator((int)$d['zone_id'], (int)$d['district_id']);
            $zn = $d['zone']; $title = ucfirst($d['item_type']) . ' · ' . $zn;
            q("INSERT INTO relief_missions (disaster_id,title,item_type,unit,target_qty,budget,status,priority,ngo_id,coordinator_id,created_by,demand_id,zone_id) VALUES (?,?,?,?,?,0,'assigned',?,?,?,?,?,?)", [(int)$d['disaster_id'], $title, $d['item_type'], $d['unit'], (int)$d['quantity'], $d['priority'], $ngo, $co, (int)$u['id'], (int)$d['id'], (int)$d['zone_id']]);
            $mid = (int)db()->lastInsertId(); q('UPDATE relief_missions SET code=? WHERE id=?', [mission_code($mid), $mid]);
            q("UPDATE demands SET status='matched' WHERE id=?", [(int)$d['id']]);
            audit('demand.match', 'demand', (int)$d['id'], $d['code'] . ' assigned to NGO #' . $ngo);
            notify_user($ngo, 'New mission assignment', mission_code($mid) . ' · ' . $zn . ': ' . number_format($d['quantity']) . ' ' . $d['unit'] . ' of ' . $d['item_type'] . ' (' . ucfirst($d['priority']) . ')', 'missions.php', 'mission');
            flash('success', 'Demand matched. The NGO team must accept it to lock the zone.');
        }
    } elseif ($a === 'unassign') {
        $m = row("SELECT id FROM relief_missions WHERE demand_id=? AND status='assigned'", [(int)$d['id']]);
        if (!$m) flash('error', 'There is no waiting assignment to withdraw.');
        else { close_mission((int)$m['id'], 'cancelled', 'Assignment withdrawn by admin'); audit('demand.unassign', 'demand', (int)$d['id'], $d['code']); flash('success', 'Assignment withdrawn. The demand is pending again.'); }
    } elseif ($a === 'cancel') {
        if (!in_array($d['status'], ['pending', 'matched'], true)) flash('error', 'This demand is already on its way.');
        else {
            $m = row("SELECT id FROM relief_missions WHERE demand_id=? AND status IN ('assigned','accepted')", [(int)$d['id']]); if ($m) close_mission((int)$m['id'], 'cancelled', 'Demand cancelled');
            q("UPDATE demands SET status='cancelled' WHERE id=?", [(int)$d['id']]); audit('demand.cancel', 'demand', (int)$d['id'], $d['code']); flash('success', 'Demand cancelled.');
        }
    }
    redirect('dashboard/demands.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$match = $admin && ($mid = (int)($_GET['match'] ?? 0)) ? demand_in_scope($u, $mid) : null;
$st = $_GET['status'] ?? ($admin ? 'open' : 'all'); $pr = $_GET['priority'] ?? '';
$w = ''; $p = [];
if ($role === 'district_admin') { $w .= ' AND d.district_id=?'; $p[] = (int)$u['district_id']; }
if ($role === 'coordinator') { $w .= ' AND d.reported_by=?'; $p[] = (int)$u['id']; }
if ($st === 'open') $w .= " AND d.status IN ('pending','matched','dispatched')"; elseif (in_array($st, ['pending', 'matched', 'dispatched', 'delivered', 'cancelled'], true)) { $w .= ' AND d.status=?'; $p[] = $st; } else $st = 'all';
if (in_array($pr, PRIORITIES, true)) { $w .= ' AND d.priority=?'; $p[] = $pr; } else $pr = '';
$list = rows("SELECT d.*, z.name zone, di.name district, m.code mcode, m.progress, m.status mstatus, n.organization ngo, u2.first_name rf, u2.last_name rl
  FROM demands d JOIN zones z ON z.id=d.zone_id JOIN districts di ON di.id=d.district_id LEFT JOIN relief_missions m ON m.demand_id=d.id AND m.status<>'cancelled' LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN users u2 ON u2.id=d.reported_by
  WHERE 1=1$w ORDER BY d.status IN ('delivered','cancelled'), FIELD(d.priority,'critical','high','medium','low'), d.created_at DESC LIMIT 300", $p);
$sc = $role === 'district_admin' ? ' WHERE district_id=' . (int)$u['district_id'] : ($role === 'coordinator' ? ' WHERE reported_by=' . (int)$u['id'] : '');
$k = row("SELECT COUNT(*) total, SUM(priority='critical' AND status='pending') crit, SUM(status='pending') pend, SUM(status='dispatched') disp, SUM(status='delivered') dlv FROM demands$sc");
dash_head($role === 'super_admin' ? 'Demand Monitoring' : ($admin ? 'Demand Management' : 'Demand History'), 'demands', $admin ? 'Match field demands with NGO stock and teams' : 'Everything you have reported');
?>
<div class="kpis"><?= kpi('', 'Total demands', (int)$k['total'], $admin ? 'All statuses' : 'Reported by you', 'clipboard', 'b') . ($admin ? kpi('red', 'Critical pending', (int)$k['crit'], 'Need immediate matching', 'alert', 'r') : kpi('green', 'Delivered', (int)$k['dlv'], 'Verified delivered', 'check', 'g')) . kpi('amber', 'Pending', (int)$k['pend'], 'Awaiting a team', 'clock', 'a') . kpi('green', 'Dispatched', (int)$k['disp'], 'On the way', 'truck', 'g') ?></div>
<?php if ($admin && (int)$k['crit']): ?><div class="alert red"><span><b><?= (int)$k['crit'] ?> critical demand<?= $k['crit'] > 1 ? 's' : '' ?> await immediate assignment.</b><span class="sub">Open "Match" to see which team has stock closest to the zone.</span></span><a class="btn btn-red btn-sm" href="?status=pending&priority=critical">Show critical</a></div><?php endif; ?>

<?php if ($match): $cands = inventory_candidates($match['item_type'], (int)$match['quantity'], (float)$match['zlat'], (float)$match['zlng']); $holder = zone_lock_holder((int)$match['zone_id'], $match['item_type']); ?>
<section class="panel"><header><div><h2>Match <?= e($match['code']) ?></h2><span class="sub"><?= e($match['zone']) ?> · <?= number_format($match['quantity']) ?> <?= e($match['unit']) ?> of <?= e($match['item_type']) ?> · <?= (int)$match['households'] ?> households · <?= e($match['access_mode']) ?></span></div><a class="btn btn-outline btn-sm" href="?">Close</a></header>
<?php if ($holder): ?><div class="lockbar" style="margin:1rem 1.3rem"><?= icon('lock') ?><div><b>Zone locked</b>This zone is already held for <?= e($match['item_type']) ?> by <?= e($holder['organization']) ?> (<?= e($holder['mcode']) ?>). No other team can be assigned.</div></div><?php endif; ?>
<table class="tbl"><thead><tr><th>Team</th><th>Best matching stock</th><th class="num">Available</th><th class="num">Distance to zone</th><th>Fit</th><th style="text-align:right">Assign</th></tr></thead><tbody>
<?php foreach ($cands as $c): ?><tr><td><b><?= e($c['organization']) ?></b></td><td><?= e($c['item']) ?><small><?= e($c['warehouse']) ?></small></td><td class="num"><?= number_format($c['available']) ?> <?= e($c['unit']) ?></td><td class="num"><?= $c['distance'] !== null ? round($c['distance']) . ' km' : '—' ?></td><td><?= $c['sufficient'] ? '<span class="badge b-green">Sufficient</span>' : '<span class="badge b-amber">Partial (' . pct($c['available'], $match['quantity']) . '%)</span>' ?></td>
  <td><div class="actions"><?php if ($match['status'] === 'pending' && !$holder): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= (int)$match['id'] ?>"><input type="hidden" name="ngo_id" value="<?= (int)$c['ngo_id'] ?>"><button class="btn btn-primary btn-xs">Assign team</button></form><?php endif; ?></div></td></tr>
<?php endforeach; if (!$cands) echo empty_row(6, 'No NGO has ' . $match['item_type'] . ' stock available. Teams can still accept after restocking.'); ?></tbody></table></section>
<?php endif; ?>

<form class="filter-bar" method="get"><?php tabs($admin ? ['open' => 'Open', 'pending' => 'Pending', 'matched' => 'Matched', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'all' => 'All'] : ['all' => 'All', 'pending' => 'Pending', 'matched' => 'Matched', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered'], $st); ?>
  <input type="hidden" name="status" value="<?= e($st) ?>"><select name="priority" onchange="this.form.submit()" aria-label="Priority"><option value="">Any priority</option><?= opts(PRIORITIES, $pr) ?></select></form>
<section class="panel"><table class="tbl"><thead><tr><th>Demand</th><th><?= $admin ? 'District / Zone' : 'Zone' ?></th><th>Type</th><th class="num">Quantity</th><th>Priority</th><th>Status</th><th><?= $admin ? 'Reporter' : 'Mission' ?></th><?php if ($admin): ?><th style="text-align:right">Actions</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($list as $d): ?><tr><td><code><?= e($d['code']) ?></code><small><?= ago($d['created_at']) ?></small></td><td><b><?= e($d['zone']) ?></b><?php if ($admin): ?><small><?= e($d['district']) ?></small><?php endif; ?></td><td><span class="chip"><?= e(ucfirst($d['item_type'])) ?></span></td><td class="num"><?= number_format($d['quantity']) ?> <small style="display:inline"><?= e($d['unit']) ?></small><small><?= (int)$d['households'] ?> households</small></td><td><?= badge($d['priority']) ?></td><td><?= badge($d['status']) ?></td>
  <td><?php if ($admin): ?><?= e(trim($d['rf'] . ' ' . $d['rl']) ?: '—') ?><?php if ($d['ngo']): ?><small><?= e($d['ngo']) ?> · <?= e($d['mcode']) ?></small><?php endif; ?><?php else: ?><?= $d['mcode'] ? '<code>' . e($d['mcode']) . '</code><small>' . e($d['ngo']) . ' · ' . (int)$d['progress'] . '%</small>' : '<small>Not matched yet</small>' ?><?php endif; ?></td>
  <?php if ($admin): ?><td><div class="actions"><?php if ($d['status'] === 'pending'): ?><a class="btn btn-primary btn-xs" href="?match=<?= (int)$d['id'] ?>">Match</a><?php endif; ?>
    <?php if ($d['status'] === 'matched' && $d['mstatus'] === 'assigned'): ?><form method="post" data-confirm="Withdraw this assignment?"><?= csrf_field() ?><input type="hidden" name="action" value="unassign"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-outline btn-xs">Withdraw</button></form><?php endif; ?>
    <?php if (in_array($d['status'], ['pending', 'matched'], true)): ?><form method="post" data-confirm="Cancel this demand?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-outline btn-xs" style="color:var(--red-d)">Cancel</button></form><?php endif; ?></div></td><?php endif; ?></tr>
<?php endforeach; if (!$list) echo empty_row($admin ? 8 : 7, 'No demands in this view.'); ?></tbody></table></section>
<?php dash_foot();
