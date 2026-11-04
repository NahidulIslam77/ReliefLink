<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['district_admin']); $did = (int)$u['district_id'];
$ppl = (int)val("SELECT COALESCE(SUM(dl.recipients),0) FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id JOIN disasters d ON d.id=m.disaster_id WHERE dl.status='verified' AND d.district_id=?", [$did]);
$mis = (int)val("SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE d.district_id=? AND m.status<>'cancelled'", [$did]);
$resp = val("SELECT AVG(TIMESTAMPDIFF(MINUTE, dm.created_at, m.dispatched_at)) FROM demands dm JOIN relief_missions m ON m.demand_id=dm.id WHERE dm.district_id=? AND m.dispatched_at IS NOT NULL", [$did]);
$dem = row("SELECT COUNT(*) n, SUM(status='delivered') d FROM demands WHERE district_id=? AND status<>'cancelled'", [$did]);
$types = ['food', 'water', 'medicine', 'shelter']; $cols = ['food' => '#2F6BFF', 'water' => '#14A34A', 'medicine' => '#F27A1A', 'shelter' => '#7A4DFF'];
$days = []; for ($i = 6; $i >= 0; $i--) { $dd = date('Y-m-d', strtotime("-$i day")); foreach ($types as $t) $days[$dd][$t] = 0; }
foreach (rows("SELECT DATE(created_at) dt, item_type t, COUNT(*) c FROM demands WHERE district_id=? AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY dt, t", [$did]) as $r) if (isset($days[$r['dt']][$r['t']])) $days[$r['dt']][$r['t']] = (int)$r['c'];
$trend = []; for ($i = 13; $i >= 0; $i--) $trend[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (rows("SELECT DATE(m.created_at) dt, COUNT(*) c FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE d.district_id=? AND m.created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY dt", [$did]) as $r) if (isset($trend[$r['dt']])) $trend[$r['dt']] = (int)$r['c'];
$zones = rows("SELECT z.name, COALESCE(SUM(m.target_qty),0) t, COALESCE(SUM(m.delivered_qty),0) dv FROM zones z LEFT JOIN relief_missions m ON m.zone_id=z.id AND m.status<>'cancelled' WHERE z.district_id=? GROUP BY z.id, z.name ORDER BY z.name", [$did]);
dash_head('District Analytics', 'analytics', $u['district_name'] . ' district');
?>
<div class="kpis"><?= kpi('blue', 'Beneficiaries served', $ppl, 'Verified deliveries', 'users', 'b') . kpi('', 'Total missions', $mis, 'Excluding cancelled', 'zap', 'b') . kpi('green', 'Avg response time', $resp !== null ? round($resp / 60, 1) . 'h' : '—', 'Demand to dispatch', 'clock', 'g') . kpi('amber', 'Demand fulfilment', pct($dem['d'] ?? 0, $dem['n'] ?? 0) . '%', ((int)$dem['d']) . ' of ' . (int)$dem['n'] . ' demands delivered', 'check', 'a') ?></div>
<div class="cols c11"><section class="panel"><header><h2>Weekly demand by type</h2></header><div class="chart-wrap chart" data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => array_map(fn($d) => date('D', strtotime($d)), array_keys($days)), 'series' => array_map(fn($t) => ['name' => ucfirst($t), 'values' => array_column($days, $t), 'color' => $cols[$t]], $types)])) ?>'></div></section>
<section class="panel"><header><h2>Mission trend · 14 days</h2></header><div class="chart-wrap chart" data-chart='<?= e(json_encode(['type' => 'line', 'labels' => array_map(fn($d) => date('M j', strtotime($d)), array_keys($trend)), 'series' => [['name' => 'Missions created', 'values' => array_values($trend), 'color' => '#4F2BE0']]])) ?>'></div></section></div>
<section class="panel"><header><h2>Zone coverage</h2><span class="sub">Verified delivered vs mission targets</span></header><div class="bars"><?php foreach ($zones as $z): $p = pct($z['dv'], $z['t']); ?><div class="r"><div><span><?= e($z['name']) ?></span><b><?= $z['t'] ? $p . '%' : 'No missions' ?></b></div><div class="bar"><i class="<?= $p >= 70 ? 'g' : ($p >= 30 ? 'o' : 'r') ?>" style="width:<?= $p ?>%"></i></div></div><?php endforeach; if (!$zones) echo '<div class="empty">No zones yet.</div>'; ?></div></section>
<?php dash_foot();
