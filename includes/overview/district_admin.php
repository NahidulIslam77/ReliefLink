<?php
$did = (int)$u['district_id'];
$sos = row("SELECT s.* FROM sos_requests s WHERE s.status='new' AND s.district_id=? ORDER BY s.severity='critical' DESC, s.created_at DESC LIMIT 1", [$did]);
$zones = rows("SELECT z.*, (SELECT n.organization FROM zone_locks l JOIN users n ON n.id=l.ngo_id WHERE l.zone_id=z.id AND l.active_key=1 LIMIT 1) ngo,
   (SELECT COUNT(*) FROM zone_locks l WHERE l.zone_id=z.id AND l.active_key=1) locks, (SELECT COUNT(*) FROM demands d WHERE d.zone_id=z.id AND d.status='pending') pend FROM zones z WHERE z.district_id=? ORDER BY FIELD(z.severity,'critical','high','medium','low'), z.name", [$did]);
$activeZones = count(array_filter($zones, fn($z) => $z['status'] === 'active'));
$missions = (int)val("SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE d.district_id=? AND m.status IN ('accepted','in_transit','arrived')", [$did]);
$pend = (int)val("SELECT COUNT(*) FROM demands WHERE status='pending' AND district_id=?", [$did]);
$people = (int)val("SELECT COALESCE(SUM(dl.recipients),0) FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id JOIN disasters d ON d.id=m.disaster_id WHERE dl.status='verified' AND d.district_id=?", [$did]);
$dem = rows("SELECT d.*, z.name zone FROM demands d JOIN zones z ON z.id=d.zone_id WHERE d.district_id=? ORDER BY d.created_at DESC LIMIT 6", [$did]);
$pts = array_map(fn($z) => ['name' => $z['name'], 'lat' => $z['lat'], 'lng' => $z['lng'], 'severity' => $z['severity'], 'status' => $z['status'], 'locked' => $z['locks'] ? $z['ngo'] : null], $zones);
dash_head('Dashboard', 'index', $u['district_name'] . ' district');
if ($sos): ?><div class="alert red"><span><?= icon('alert') ?> <b>Unresponded SOS: <?= e($sos['location']) ?></b><span class="sub"><?= e(mb_substr((string)$sos['description'], 0, 140)) ?> · <?= ago($sos['created_at']) ?></span></span><a href="<?= url('dashboard/sos.php') ?>" style="font-weight:700">Respond →</a></div><?php endif; ?>
<div class="kpis"><?= kpi('red', 'Active Zones', $activeZones, $u['district_name'] . ' District', 'pin', 'r') . kpi('blue', 'Active Missions', $missions, 'Today', 'zap', 'b') . kpi('amber', 'Pending Demands', $pend, 'Awaiting action', 'clipboard', 'a') . kpi('green', 'Beneficiaries', $people, 'Served this event', 'check', 'g') ?></div>
<div class="cols c21">
  <section class="panel"><header><h2><?= e($u['district_name']) ?> District: Zone Map</h2><a href="<?= url('dashboard/zones.php') ?>" style="font-size:13px">Manage zones →</a></header>
    <div class="mapbox" data-zones='<?= e(json_encode($pts)) ?>'></div>
    <div class="legend" style="padding:.8rem 1.3rem;margin:0"><span><i style="background:var(--red)"></i>Critical zone</span><span><i style="background:var(--orange)"></i>High</span><span><i style="background:var(--amber)"></i>Medium</span><span><i style="background:#8A98B8"></i>Monitoring</span><span>🔒 Zone locked by an NGO</span></div></section>
  <section class="panel"><header><h2>Zone Status</h2></header>
    <?php foreach ($zones as $z): $st = $z['status'] === 'monitoring' ? 'monitoring' : ($z['locks'] ? 'active' : ($z['pend'] ? 'pending' : 'active')); ?>
      <div class="list-row"><span><i class="sev-dot sev-<?= e($z['severity']) ?>"></i><b><?= e($z['name']) ?></b><small><?= $z['ngo'] ? e($z['ngo']) : '<span style="color:var(--red-d);font-weight:600">⚠ Unassigned</span>' ?></small></span><?= $st === 'monitoring' ? badge('monitoring') : ($st === 'pending' ? badge('pending') : '<span class="badge b-green">Active</span>') ?></div>
    <?php endforeach; if (!$zones): ?><div class="empty">No zones yet. <a href="<?= url('dashboard/zones.php') ?>">Add one</a>.</div><?php endif; ?></section>
</div>
<section class="panel"><header><h2>Recent Demands</h2><a href="<?= url('dashboard/demands.php') ?>" style="font-size:13px">All demands →</a></header>
<table class="tbl"><thead><tr><th>ID</th><th>Zone</th><th>Type</th><th>Quantity</th><th>Priority</th><th>Status</th><th>Reported</th></tr></thead><tbody>
<?php foreach ($dem as $d): ?><tr><td><code><?= e($d['code']) ?></code></td><td><b><?= e($d['zone']) ?></b></td><td><span class="chip"><?= e(ucfirst($d['item_type'])) ?></span></td><td><?= number_format($d['quantity']) ?> <?= e($d['unit']) ?></td><td><?= badge($d['priority']) ?></td><td><?= badge($d['status']) ?></td><td><small style="display:inline"><?= ago($d['created_at']) ?></small></td></tr><?php endforeach; if (!$dem) echo empty_row(7, 'No demands reported yet.'); ?></tbody></table></section>
<?php dash_foot();
