<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator']);
$role = $u['role']; [$msc, $mpc] = mission_scope($u);
$sel = (int)($_GET['id'] ?? 0);
$q = "SELECT m.*, d.district_id, z.name zone, z.lat zlat, z.lng zlng, n.organization ngo, v.plate, t.name driver, i.warehouse, w.lat wlat, w.lng wlng FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN vehicles v ON v.id=m.vehicle_id LEFT JOIN team_members t ON t.id=m.driver_id LEFT JOIN inventory i ON i.id=m.inventory_id LEFT JOIN districts w ON w.id=i.district_id";

if (is_post() && $role === 'ngo') {
    csrf_check(); $a = post('action');
    $m = row("$q WHERE m.id=? AND m.ngo_id=?", [(int)post('id'), (int)$u['id']]);
    $lat = in_bangladesh(post('lat'), post('lng')) ? (float)post('lat') : null; $lng = $lat !== null ? (float)post('lng') : null;
    if (!$m || $m['status'] !== 'in_transit') flash('error', 'Only missions that are in transit can be updated.');
    elseif ($a === 'checkpoint') {
        $pr = max((int)$m['progress'], min(95, (int)post('progress'))); $note = post('note');
        if ($note === '') flash('error', 'Add a short checkpoint note.');
        else { q('UPDATE relief_missions SET progress=? WHERE id=?', [$pr, (int)$m['id']]); mission_event((int)$m['id'], 'in_transit', $note, $lat, $lng); flash('success', 'Checkpoint added.'); }
    } elseif ($a === 'arrived') {
        q("UPDATE relief_missions SET status='arrived', progress=100, arrived_at=NOW() WHERE id=?", [(int)$m['id']]); mission_event((int)$m['id'], 'arrived', post('note') ?: 'Arrived at the destination zone', $lat, $lng);
        audit('mission.arrived', 'mission', (int)$m['id'], $m['title']);
        if ($m['coordinator_id']) notify_user((int)$m['coordinator_id'], 'Relief has arrived at your zone', $m['title'] . '. Confirm receipt and give the NGO team your 6-digit code.', 'confirm.php', 'mission');
        flash('success', 'Marked as arrived. Ask the Spot Coordinator for the confirmation code, then submit your delivery evidence.');
        redirect('dashboard/confirm.php');
    }
    redirect('dashboard/tracking.php?id=' . (int)$m['id']);
}

$m = $sel ? row("$q WHERE m.id=?$msc", array_merge([$sel], $mpc)) : null;
if (!$m) {
    $list = rows("$q WHERE m.status IN ('in_transit','arrived')$msc ORDER BY m.dispatched_at DESC", $mpc);
    dash_head('Live Tracking', 'tracking', count($list) . ' mission' . (count($list) === 1 ? '' : 's') . ' on the road');
    ?><div class="card-list"><?php foreach ($list as $x): ?><a class="dl-card" href="?id=<?= (int)$x['id'] ?>"><span style="flex:1"><span class="row"><span class="code"><?= e($x['code']) ?></span><?= badge($x['status']) ?><?= badge($x['priority']) ?></span><h4><?= e($x['title']) ?></h4><small><?= e($x['ngo']) ?> · <?= e($x['plate'] ?: 'no vehicle') ?> · <?= e($x['zone']) ?><?= $x['eta'] ? ' · ETA ' . e(date('H:i', strtotime($x['eta']))) : '' ?></small><div class="bar" style="margin-top:.6rem"><i style="width:<?= (int)$x['progress'] ?>%"></i></div></span><b style="font-size:1.2rem;color:var(--blue)"><?= (int)$x['progress'] ?>%</b></a><?php endforeach; ?></div>
    <?php if (!$list): ?><div class="panel"><div class="empty">No missions are on the road right now.<?= $role === 'ngo' ? ' <a href="' . e(url('dashboard/dispatch.php')) . '">Dispatch one</a>.' : '' ?></div></div><?php endif; dash_foot(); exit;
}
$events = rows('SELECT e.*, u.first_name FROM mission_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.mission_id=? ORDER BY e.id', [(int)$m['id']]);
$last = null; foreach ($events as $e) if ($e['lat'] !== null) $last = $e;
$from = $m['wlat'] !== null ? ['lat' => $m['wlat'], 'lng' => $m['wlng'], 'label' => $m['warehouse']] : null;
$route = ['from' => $from, 'to' => ['lat' => $m['zlat'], 'lng' => $m['zlng'], 'label' => $m['zone']], 'progress' => (int)$m['progress'], 'now' => $m['status'] === 'in_transit' && $last ? ['lat' => $last['lat'], 'lng' => $last['lng']] : null];
$stage = $m['status'] === 'arrived' ? (val("SELECT COUNT(*) FROM deliveries WHERE mission_id=?", [(int)$m['id']]) ? 3 : 2) : ($m['status'] === 'completed' ? 4 : 1);
$labels = ['Dispatched', 'In transit', 'Arrived', 'Awaiting confirmation', 'Delivered & verified'];
dash_head('Live Tracking', 'tracking', $m['code'] . ' · ' . $m['zone']);
?>
<div class="page-actions"><a href="<?= url('dashboard/tracking.php') ?>" style="font-size:14px">← All missions on the road</a><?php if ($role === 'ngo' && in_array($m['status'], ['in_transit', 'arrived'], true)): ?><a class="btn btn-green btn-sm" href="<?= url('dashboard/confirm.php') ?>">Confirm delivery</a><?php endif; ?></div>
<section class="panel"><div class="steps-line"><?php foreach ($labels as $i => $l): ?><div class="s <?= $i < $stage ? 'done' : ($i === $stage ? 'now' : '') ?>"><i></i><?= e($l) ?></div><?php endforeach; ?></div></section>
<div class="cols c32"><section class="panel"><header><h2>Route map</h2><span class="badge b-blue"><?= $m['eta'] ? 'ETA ' . e(date('H:i', strtotime($m['eta']))) : 'No ETA' ?></span></header><div class="mapbox route" data-route='<?= e(json_encode($route)) ?>'></div></section>
  <section class="panel"><header><h2>Mission status</h2><?= badge($m['status']) ?></header><div class="kv one"><div><span>Vehicle</span><b><?= e($m['plate'] ?: '—') ?></b></div><div><span>Driver</span><b><?= e($m['driver'] ?: '—') ?></b></div><div><span>Cargo</span><b><?= e($m['cargo'] ?: '—') ?></b></div><div><span>Team</span><b><?= e($m['ngo']) ?></b></div><div><span>Progress</span><b style="color:var(--blue)"><?= (int)$m['progress'] ?>%</b></div><div><span>Dispatched</span><b><?= e(dt_label($m['dispatched_at'])) ?></b></div><div><span>ETA</span><b><?= e(dt_label($m['eta'])) ?></b></div></div></section></div>
<div class="cols c11"><section class="panel"><header><h2>Route checkpoints</h2></header><div class="tl-v"><?php foreach (array_reverse($events) as $i => $e): ?><div class="e"><span class="d <?= $i === 0 && $m['status'] === 'in_transit' ? 'now' : '' ?>"></span><div><time><?= e(date('j M, H:i', strtotime($e['created_at']))) ?></time><b><?= e(ucwords(str_replace('_', ' ', $e['status']))) ?></b><span style="color:var(--muted);font-size:13.5px"><?= e($e['note']) ?></span></div></div><?php endforeach; if (!$events) echo '<div class="empty">No checkpoints yet.</div>'; ?></div></section>
<?php if ($role === 'ngo' && $m['status'] === 'in_transit'): ?><section class="panel"><header><h2>Update your position</h2></header>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
    <div class="field"><label for="note">Checkpoint note</label><input type="text" id="note" name="note" maxlength="240" placeholder="e.g. Crossed Osmani Bridge, switching to boat"></div>
    <div class="field"><label for="progress">Progress: <output id="po"><?= (int)$m['progress'] ?></output>%</label><input type="range" id="progress" name="progress" min="<?= (int)$m['progress'] ?>" max="95" value="<?= (int)$m['progress'] ?>" oninput="po.value=this.value" style="padding:0"></div>
    <div class="field"><label>Location (optional)</label><div class="geo-row"><input type="text" id="lat" name="lat" placeholder="Latitude"><input type="text" id="lng" name="lng" placeholder="Longitude"><button type="button" class="btn btn-outline" data-geo="lat,lng,gs"><?= icon('pin') ?>Detect</button></div><div class="geo-state" id="gs"></div></div>
    <div style="display:flex;gap:.6rem"><button class="btn btn-primary" name="action" value="checkpoint">Add checkpoint</button><button class="btn btn-green" name="action" value="arrived" data-arrive>Mark arrived</button></div></form></section><?php endif; ?></div>
<?php dash_foot();
