<?php
$sos = row("SELECT s.*, di.name district FROM sos_requests s JOIN districts di ON di.id=s.district_id WHERE s.status='new' ORDER BY s.severity='critical' DESC, s.created_at DESC LIMIT 1");
$active = (int)val("SELECT COUNT(*) FROM disasters WHERE status='active'");
$divs = (int)val("SELECT COUNT(DISTINCT di.division) FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status='active'");
$deployed = (int)val("SELECT COUNT(*) FROM relief_missions WHERE status IN ('accepted','in_transit','arrived')");
$pend = (int)val("SELECT COUNT(*) FROM demands WHERE status='pending'");
$people = (int)val("SELECT COALESCE(SUM(recipients),0) FROM deliveries WHERE status='verified'");
$points = rows("SELECT d.title, d.severity, d.status, d.affected_people AS people, di.name, di.lat, di.lng FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status='active'");
$top = rows("SELECT d.title, d.severity, d.affected_people, di.name, (SELECT COUNT(*) FROM zones z WHERE z.disaster_id=d.id) zc FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status='active' ORDER BY FIELD(d.severity,'critical','high','medium','low'), d.affected_people DESC LIMIT 6");
$divm = rows("SELECT di.division, COUNT(*) c FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id JOIN districts di ON di.id=d.district_id GROUP BY di.division ORDER BY c DESC LIMIT 3");
$days = []; for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = [0, 0];
foreach (rows("SELECT DATE(created_at) dt, COUNT(*) c FROM relief_missions WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY dt") as $r) if (isset($days[$r['dt']])) $days[$r['dt']][0] = (int)$r['c'];
foreach (rows("SELECT DATE(created_at) dt, SUM(recipients) c FROM deliveries WHERE status='verified' AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY dt") as $r) if (isset($days[$r['dt']])) $days[$r['dt']][1] = (int)$r['c'];
$byType = rows("SELECT item_type t, COUNT(*) c FROM demands WHERE status<>'cancelled' GROUP BY item_type ORDER BY c DESC");
$missions = rows("SELECT m.*, di.name district, z.name zone, n.organization ngo FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id JOIN districts di ON di.id=d.district_id LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN users n ON n.id=m.ngo_id ORDER BY m.created_at DESC LIMIT 6");
dash_head('Dashboard', 'index', 'National operations overview');
if ($sos): ?><div class="alert red"><span><?= icon('alert') ?> <b>SOS Active: <?= e($sos['location']) ?>, <?= e($sos['district']) ?></b><span class="sub"><?= e(mb_substr((string)$sos['description'], 0, 140)) ?> · <?= ago($sos['created_at']) ?></span></span><a href="<?= url('dashboard/sos.php') ?>" style="font-weight:700">View →</a></div><?php endif; ?>
<div class="kpis"><?= kpi('red', 'Active Disasters', $active, "Across $divs divisions", 'alert', 'r') . kpi('blue', 'Active Missions', $deployed, 'Currently deployed', 'zap', 'b') . kpi('amber', 'Pending Demands', $pend, 'Awaiting fulfilment', 'clipboard', 'a') . kpi('green', 'Beneficiaries Served', $people, 'Verified deliveries', 'check', 'g') ?></div>
<div class="cols c21">
  <section class="panel"><header><h2>National Operations Map</h2><a href="<?= url('dashboard/zones.php') ?>" style="font-size:13px">Full view →</a></header>
    <div class="mapbox" data-map='<?= e(json_encode($points)) ?>'></div>
    <div class="stat3"><?php foreach ($divm as $d): ?><div><b><?= (int)$d['c'] ?></b><?= e($d['division']) ?> Div. missions</div><?php endforeach; ?></div></section>
  <section class="panel"><header><h2>Active Disasters</h2><a href="<?= url('dashboard/disasters.php') ?>" style="font-size:13px">All →</a></header>
    <?php foreach ($top as $d): ?><div class="list-row"><span><i class="sev-dot sev-<?= e($d['severity']) ?>"></i><b><?= e($d['title']) ?></b><small><?= (int)$d['zc'] ?> zones · <?= number_format($d['affected_people']) ?> affected · <?= e($d['name']) ?></small></span><?= badge($d['severity']) ?></div><?php endforeach; if (!$top): ?><div class="empty">No active disasters.</div><?php endif; ?></section>
</div>
<div class="cols c21">
  <section class="panel"><header><h2>Relief Missions &amp; Beneficiaries · last 14 days</h2></header><div class="chart-wrap chart" data-chart='<?= e(json_encode(['type' => 'line', 'labels' => array_map(fn($d) => date('M j', strtotime($d)), array_keys($days)), 'series' => [['name' => 'Missions created', 'values' => array_column($days, 0), 'color' => '#2F6BFF'], ['name' => 'People reached (verified)', 'values' => array_column($days, 1), 'color' => '#14A34A']]])) ?>'></div></section>
  <section class="panel"><header><h2>Demand by Type</h2></header><div class="chart-wrap chart" data-chart='<?= e(json_encode(['type' => 'donut', 'labels' => array_map('ucfirst', array_column($byType, 't')), 'series' => [['name' => 'Demands', 'values' => array_map('intval', array_column($byType, 'c'))]]])) ?>'></div></section>
</div>
<section class="panel"><header><h2>Recent Missions</h2><a href="<?= url('dashboard/missions.php') ?>" style="font-size:13px">View all →</a></header>
<table class="tbl"><thead><tr><th>Mission ID</th><th>District</th><th>Zone</th><th>Team</th><th>Type</th><th>Progress</th><th>Status</th></tr></thead><tbody>
<?php foreach ($missions as $m): $p = pct($m['delivered_qty'], $m['target_qty']); ?><tr><td><code><?= e($m['code']) ?></code></td><td><b><?= e($m['district']) ?></b></td><td><?= e($m['zone'] ?: '—') ?></td><td><?= e($m['ngo'] ?: 'Unassigned') ?></td><td><span class="chip"><?= e(ucfirst($m['item_type'])) ?></span></td>
  <td class="prog"><div class="bar"><i style="width:<?= $p ?>%"></i></div><small><?= $p ?>%</small></td><td><?= badge($m['status']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php dash_foot();
