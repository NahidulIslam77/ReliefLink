<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin']);
$isSuper = $u['role'] === 'super_admin';

if (is_post()) {
    csrf_check(); $a = post('action');
    if ($a === 'add') {
        $did = $isSuper ? (int)post('district_id') : (int)$u['district_id']; $dist = row('SELECT * FROM districts WHERE id=?', [$did]);
        $dis = (int)post('disaster_id') ?: null; $name = post('name');
        $lat = is_numeric(post('lat')) ? (float)post('lat') : ($dist['lat'] ?? 0); $lng = is_numeric(post('lng')) ? (float)post('lng') : ($dist['lng'] ?? 0);
        if (!$dist) flash('error', 'Select a district.');
        elseif ($name === '' || mb_strlen($name) > 100) flash('error', 'Enter a zone name.');
        elseif ($dis && !val('SELECT COUNT(*) FROM disasters WHERE id=? AND district_id=?', [$dis, $did])) flash('error', 'That disaster is not in this district.');
        elseif (!in_bangladesh($lat, $lng)) flash('error', 'Coordinates must be inside Bangladesh.');
        elseif (!in_array(post('severity'), SEVERITIES, true)) flash('error', 'Choose a severity.');
        else {
            q('INSERT INTO zones (district_id,disaster_id,name,severity,status,affected_people,households,lat,lng) VALUES (?,?,?,?,?,?,?,?,?)', [$did, $dis, $name, post('severity'), 'active', max(0, (int)post('affected_people')), max(0, (int)post('households')), $lat, $lng]);
            $id = (int)db()->lastInsertId(); q('UPDATE zones SET code=? WHERE id=?', ['ZN-' . str_pad((string)$id, 2, '0', STR_PAD_LEFT), $id]);
            audit('zone.add', 'zone', $id, $name); flash('success', 'Zone added.');
        }
    } elseif ($a === 'update') {
        $z = row('SELECT * FROM zones WHERE id=?', [(int)post('id')]);
        if (!$z || (!$isSuper && (int)$z['district_id'] !== (int)$u['district_id'])) flash('error', 'Zone not found in your district.');
        elseif (!in_array(post('severity'), SEVERITIES, true) || !in_array(post('status'), ['active', 'monitoring', 'resolved'], true)) flash('error', 'Invalid severity or status.');
        else { q('UPDATE zones SET severity=?, status=?, affected_people=?, households=? WHERE id=?', [post('severity'), post('status'), max(0, (int)post('affected_people')), max(0, (int)post('households')), (int)$z['id']]); audit('zone.update', 'zone', (int)$z['id'], $z['name'] . ' → ' . post('status') . '/' . post('severity')); flash('success', 'Zone updated.'); }
    }
    redirect('dashboard/zones.php' . (isset($_GET['district']) ? '?district=' . (int)$_GET['district'] : ''));
}

$fd = $isSuper ? (int)($_GET['district'] ?? 0) : (int)$u['district_id'];
$where = $fd ? ' WHERE z.district_id=?' : ''; $p = $fd ? [$fd] : [];
$zones = rows("SELECT z.*, di.name district, d.title dtitle,
  (SELECT COUNT(*) FROM demands x WHERE x.zone_id=z.id AND x.status IN ('pending','matched','dispatched')) open_dem,
  (SELECT COUNT(*) FROM relief_missions m WHERE m.zone_id=z.id AND m.status NOT IN ('cancelled')) missions,
  (SELECT GROUP_CONCAT(CONCAT(l.item_type,' → ',n.organization) SEPARATOR '; ') FROM zone_locks l JOIN users n ON n.id=l.ngo_id WHERE l.zone_id=z.id AND l.active_key=1) locks
  FROM zones z JOIN districts di ON di.id=z.district_id LEFT JOIN disasters d ON d.id=z.disaster_id$where ORDER BY FIELD(z.severity,'critical','high','medium','low'), di.name, z.name", $p);
$pts = array_map(fn($z) => ['name' => $z['name'], 'lat' => $z['lat'], 'lng' => $z['lng'], 'severity' => $z['severity'], 'status' => $z['status'], 'locked' => $z['locks'] ? 'NGO' : null], $zones);
$dis = rows('SELECT id, title FROM disasters WHERE status<>"resolved"' . (!$isSuper ? ' AND district_id=?' : ''), !$isSuper ? [(int)$u['district_id']] : []);
$crit = count(array_filter($zones, fn($z) => $z['severity'] === 'critical' && $z['status'] === 'active'));
dash_head('Disaster Zones', 'zones', $isSuper ? 'All affected zones nationwide' : $u['district_name'] . ' district zones');
?>
<div class="kpis"><?= kpi('', 'Zones', count($zones), $fd ? 'In this district' : 'Nationwide', 'pin', 'b') . kpi('red', 'Critical', $crit, 'Active critical zones', 'alert', 'r') . kpi('amber', 'Open demands', array_sum(array_column($zones, 'open_dem')), 'Awaiting delivery', 'clipboard', 'a') . kpi('blue', 'Locked zones', count(array_filter($zones, fn($z) => $z['locks'])), 'Held by an NGO', 'lock', 'b') ?></div>
<?php if ($isSuper): ?><form class="toolbar" method="get"><label for="district" style="font-weight:600;font-size:13.5px">District</label><select id="district" name="district" onchange="this.form.submit()"><option value="">All districts</option><?= district_options($fd) ?></select></form><?php endif; ?>
<section class="panel"><header><h2>Zone map</h2><span class="sub">🔒 = locked by an NGO team</span></header><div class="mapbox" style="height:300px" data-zones='<?= e(json_encode($pts)) ?>'></div></section>
<details class="panel"><summary>Add a zone</summary>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="two"><div class="field"><label for="name">Zone name</label><input type="text" id="name" name="name" required maxlength="100" placeholder="e.g. Sadar North Zone A"></div>
      <div class="field"><label for="district_id">District</label><?php if ($isSuper): ?><select id="district_id" name="district_id" required><?= district_options($fd) ?></select><?php else: ?><input type="text" value="<?= e($u['district_name']) ?>" disabled><?php endif; ?></div></div>
    <div class="three"><div class="field"><label for="disaster_id">Disaster</label><select id="disaster_id" name="disaster_id"><option value="">None yet</option><?php foreach ($dis as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['title']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="severity">Severity</label><select id="severity" name="severity"><?= opts(SEVERITIES, 'high') ?></select></div>
      <div class="field"><label for="ap">People affected</label><input type="number" id="ap" name="affected_people" min="0" value="0"></div></div>
    <div class="three"><div class="field"><label for="hh">Households</label><input type="number" id="hh" name="households" min="0" value="0"></div>
      <div class="field"><label for="lat">Latitude (optional)</label><input type="text" id="lat" name="lat" placeholder="district centre if blank"></div><div class="field"><label for="lng">Longitude (optional)</label><input type="text" id="lng" name="lng"></div></div>
    <button class="btn btn-primary" type="submit">Add zone</button></form></details>
<section class="panel"><table class="tbl"><thead><tr><th>Zone</th><th>Disaster</th><th>Severity</th><th>Status</th><th class="num">Affected</th><th>Demands / Missions</th><th>Locks</th><th>Update</th></tr></thead><tbody>
<?php foreach ($zones as $z): ?><tr><td><b><?= e($z['name']) ?></b><small><?= e($z['code']) ?> · <?= e($z['district']) ?></small></td><td><small style="color:var(--ink)"><?= e($z['dtitle'] ?: '—') ?></small></td><td><?= badge($z['severity']) ?></td><td><?= badge($z['status']) ?></td><td class="num"><?= number_format($z['affected_people']) ?><small><?= number_format($z['households']) ?> households</small></td>
  <td><?= (int)$z['open_dem'] ?> open · <?= (int)$z['missions'] ?> missions</td><td><small style="color:var(--ink)"><?= $z['locks'] ? '🔒 ' . e($z['locks']) : '—' ?></small></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$z['id'] ?>"><input type="hidden" name="households" value="<?= (int)$z['households'] ?>"><select name="severity" aria-label="Severity"><?= opts(SEVERITIES, $z['severity']) ?></select><select name="status" aria-label="Status"><?= opts(['active', 'monitoring', 'resolved'], $z['status']) ?></select><input type="number" name="affected_people" min="0" value="<?= (int)$z['affected_people'] ?>" aria-label="People affected"><button class="btn btn-outline btn-xs">Save</button></form></td></tr>
<?php endforeach; if (!$zones) echo empty_row(8, 'No zones yet.'); ?></tbody></table></section>
<?php dash_foot();
