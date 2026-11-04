<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin']);
$isSuper = $u['role'] === 'super_admin';

if (is_post()) {
    csrf_check();
    $a = post('action');
    if ($a === 'declare') {
        $did = $isSuper ? (int)post('district_id') : (int)$u['district_id'];
        $title = post('title'); $type = post('type'); $sev = post('severity');
        $aff = max(0, (int)post('affected_people')); $fam = max(0, (int)post('families'));
        $dist = row('SELECT * FROM districts WHERE id=?', [$did]);
        if ($title === '' || mb_strlen($title) > 160) flash('error', 'Enter a title of up to 160 characters.');
        elseif (!in_array($type, DISASTER_TYPES, true) || !in_array($sev, SEVERITIES, true)) flash('error', 'Choose a disaster type and severity.');
        elseif (!$dist) flash('error', 'Select the affected district.');
        else {
            q('INSERT INTO disasters (title,type,severity,status,district_id,affected_people,families,description,declared_by) VALUES (?,?,?,?,?,?,?,?,?)', [$title, $type, $sev, 'active', $did, $aff, $fam, post('description') ?: null, (int)$u['id']]);
            $id = (int)db()->lastInsertId();
            if (isset($_POST['make_zone'])) {
                q('INSERT INTO zones (district_id,disaster_id,name,severity,status,affected_people,households,lat,lng) VALUES (?,?,?,?,?,?,?,?,?)', [$did, $id, $dist['name'] . ' Central Zone', $sev, 'active', $aff, $fam, $dist['lat'], $dist['lng']]);
                $zid = (int)db()->lastInsertId(); q('UPDATE zones SET code=? WHERE id=?', ['ZN-' . str_pad((string)$zid, 2, '0', STR_PAD_LEFT), $zid]);
            }
            audit('disaster.declare', 'disaster', $id, $title);
            notify_where("(role IN ('district_admin','coordinator') AND district_id=? AND id<>?) OR role='audit'", [$did, (int)$u['id']], 'New disaster declared', $title . ' (' . ucfirst($sev) . ')', 'index.php', 'disaster');
            flash('success', 'Disaster declared' . (isset($_POST['make_zone']) ? ' with a default zone. Edit or add zones under Disaster Zones.' : '.'));
        }
    } elseif ($a === 'update') {
        $id = (int)post('id'); $d = row('SELECT * FROM disasters WHERE id=?', [$id]);
        if (!$d || (!$isSuper && (int)$d['district_id'] !== (int)$u['district_id'])) flash('error', 'You can only update disasters in your own district.');
        elseif (!in_array(post('severity'), SEVERITIES, true) || !in_array(post('status'), ['active', 'monitoring', 'resolved'], true)) flash('error', 'Invalid severity or status.');
        else {
            q('UPDATE disasters SET severity=?, status=?, affected_people=?, families=? WHERE id=?', [post('severity'), post('status'), max(0, (int)post('affected_people')), max(0, (int)post('families')), $id]);
            audit('disaster.update', 'disaster', $id, $d['title'] . ' → ' . post('status') . '/' . post('severity'));
            flash('success', 'Disaster updated.');
        }
    }
    redirect('dashboard/disasters.php');
}

$st = $_GET['status'] ?? 'active';
[$ds, $dp] = district_scope($u, 'd.district_id');
$where = "1=1$ds"; $p = $dp;
if (in_array($st, ['active', 'monitoring', 'resolved'], true)) { $where .= ' AND d.status=?'; $p[] = $st; } else $st = 'all';
$list = rows("SELECT d.*, di.name AS district, di.division,
   (SELECT COUNT(*) FROM zones z WHERE z.disaster_id=d.id) AS zones,
   (SELECT COUNT(*) FROM relief_missions m WHERE m.disaster_id=d.id) AS missions,
   (SELECT COALESCE(SUM(m.delivered_qty),0) FROM relief_missions m WHERE m.disaster_id=d.id) AS delivered
   FROM disasters d JOIN districts di ON di.id=d.district_id WHERE $where ORDER BY FIELD(d.severity,'critical','high','medium','low'), d.declared_at DESC", $p);
$sum = row("SELECT COUNT(*) n, COALESCE(SUM(d.affected_people),0) a FROM disasters d WHERE d.status='active'$ds", $dp);
$mc = (int)val("SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE 1=1$ds", $dp);

dash_head($isSuper ? 'Disaster Management' : 'Disasters', 'disasters', 'Declare and update disasters' . (!$isSuper ? ' in ' . $u['district_name'] : ''));
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('red', 'Total Active', (int)$sum['n'], 'Declared disasters', 'alert', 'r') . kpi('', 'Total Affected', (int)$sum['a'], 'People in active disasters', 'users', 'b') . kpi('blue', 'Total Missions', $mc, 'Linked relief missions', 'zap', 'b') ?></div>
<details class="panel"><summary>Declare a disaster</summary>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="declare">
    <div class="two"><div class="field"><label for="title">Title</label><input type="text" id="title" name="title" maxlength="160" required placeholder="e.g. Sylhet flash flood 2026"></div>
      <div class="field"><label for="district_id">District</label><?php if ($isSuper): ?><select id="district_id" name="district_id" required><?= district_options() ?></select><?php else: ?><input type="text" value="<?= e($u['district_name']) ?>" disabled><?php endif; ?></div></div>
    <div class="three"><div class="field"><label for="type">Type</label><select id="type" name="type"><?= opts(DISASTER_TYPES) ?></select></div>
      <div class="field"><label for="severity">Severity</label><select id="severity" name="severity"><?= opts(SEVERITIES, 'high') ?></select></div>
      <div class="field"><label for="ap">People affected</label><input type="number" id="ap" name="affected_people" min="0" value="0"></div></div>
    <div class="two"><div class="field"><label for="fam">Families affected</label><input type="number" id="fam" name="families" min="0" value="0"></div><div class="field"><label for="desc">Description (optional)</label><input type="text" id="desc" name="description"></div></div>
    <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="make_zone" checked> Create a default zone so coordinators can report demands right away</label>
    <button class="btn btn-primary" type="submit">Declare disaster</button></form></details>
<?php tabs(['active' => 'Active', 'monitoring' => 'Monitoring', 'resolved' => 'Resolved', 'all' => 'All'], $st); ?>
<section class="panel"><header><h2>All Disaster Events</h2><span class="sub"><?= count($list) ?> records</span></header><table class="tbl"><thead><tr><th>Disaster</th><th>Type</th><th>Severity</th><th>Status</th><th class="num">Affected</th><th class="num">Families</th><th>Zones / Missions</th><th>Update</th></tr></thead><tbody>
<?php foreach ($list as $d): ?>
  <tr><td><b><?= e($d['title']) ?></b><small><?= e($d['district']) ?>, <?= e($d['division']) ?> · declared <?= ago($d['declared_at']) ?></small></td><td><?= e(ucfirst($d['type'])) ?></td><td><?= badge($d['severity']) ?></td><td><?= badge($d['status']) ?></td>
    <td class="num"><?= number_format($d['affected_people']) ?></td><td class="num"><?= number_format($d['families']) ?></td><td><?= (int)$d['zones'] ?> zones · <?= (int)$d['missions'] ?> missions<small><?= number_format($d['delivered']) ?> units verified</small></td>
    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="families" value="<?= (int)$d['families'] ?>">
      <select name="severity" aria-label="Severity"><?= opts(SEVERITIES, $d['severity']) ?></select><select name="status" aria-label="Status"><?= opts(['active', 'monitoring', 'resolved'], $d['status']) ?></select>
      <input type="number" name="affected_people" min="0" value="<?= (int)$d['affected_people'] ?>" aria-label="People affected"><button class="btn btn-outline btn-xs" type="submit">Save</button></form></td></tr>
<?php endforeach; if (!$list) echo empty_row(8, 'No disasters in this view.'); ?></tbody></table></section>
<?php dash_foot();
