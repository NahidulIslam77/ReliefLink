<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin']);
$div = $_GET['division'] ?? ''; $divs = array_column(rows('SELECT DISTINCT division FROM districts ORDER BY division'), 'division');
if (!in_array($div, $divs, true)) $div = '';
$list = rows("SELECT di.*,
 (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) SEPARATOR ', ') FROM users a WHERE a.role='district_admin' AND a.district_id=di.id AND a.status='active') admin,
 (SELECT COUNT(*) FROM users c WHERE c.role='coordinator' AND c.district_id=di.id AND c.status='active') coords,
 (SELECT COUNT(*) FROM disasters d WHERE d.district_id=di.id AND d.status='active') dis,
 (SELECT COUNT(*) FROM zones z WHERE z.district_id=di.id) zones,
 (SELECT COUNT(*) FROM demands x WHERE x.district_id=di.id AND x.status IN ('pending','matched','dispatched')) dem,
 (SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE d.district_id=di.id AND m.status IN ('accepted','in_transit','arrived')) mis,
 (SELECT COUNT(*) FROM sos_requests s WHERE s.district_id=di.id AND s.status<>'resolved') sos
 FROM districts di" . ($div ? ' WHERE di.division=?' : '') . " ORDER BY di.division, di.name", $div ? [$div] : []);
$noAdmin = count(array_filter($list, fn($d) => !$d['admin'] && $d['dis'] > 0));
dash_head('District Management', 'districts', '64 districts · 8 divisions');
?>
<div class="kpis"><?= kpi('', 'Districts', count($list), $div ?: 'All divisions', 'grid', 'b') . kpi('red', 'Affected', count(array_filter($list, fn($d) => $d['dis'] > 0)), 'With active disasters', 'alert', 'r') . kpi('amber', 'Without an admin', $noAdmin, 'Affected, no District Admin', 'user', 'a') . kpi('blue', 'Open SOS', array_sum(array_column($list, 'sos')), 'Across selected districts', 'alert', 'b') ?></div>
<form class="toolbar" method="get"><label for="division" style="font-weight:600;font-size:13.5px">Division</label><select id="division" name="division" onchange="this.form.submit()"><option value="">All divisions</option><?php foreach ($divs as $d): ?><option <?= $div === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select><a class="btn btn-outline btn-sm" href="<?= url('dashboard/users.php') ?>">Manage District Admins</a></form>
<section class="panel"><table class="tbl"><thead><tr><th>District</th><th>District Admin</th><th class="num">Coordinators</th><th class="num">Active disasters</th><th class="num">Zones</th><th class="num">Open demands</th><th class="num">Active missions</th><th class="num">Open SOS</th></tr></thead><tbody>
<?php foreach ($list as $d): ?><tr><td><b><?= e($d['name']) ?></b><small><?= e($d['division']) ?></small></td><td><?= $d['admin'] ? e($d['admin']) : ($d['dis'] ? '<span style="color:var(--red-d);font-weight:600">⚠ Not assigned</span>' : '<small>—</small>') ?></td>
  <td class="num"><?= (int)$d['coords'] ?></td><td class="num"><?= $d['dis'] ? '<span class="badge b-red">' . (int)$d['dis'] . '</span>' : '0' ?></td><td class="num"><?= (int)$d['zones'] ?></td><td class="num"><?= (int)$d['dem'] ?></td><td class="num"><?= (int)$d['mis'] ?></td><td class="num"><?= $d['sos'] ? '<span class="badge b-amber">' . (int)$d['sos'] . '</span>' : '0' ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php dash_foot();
