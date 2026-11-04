<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['ngo', 'coordinator']);
$role = $u['role']; $id = (int)$u['id'];

if (is_post()) {
    csrf_check();
    if ($role === 'coordinator') [$ok, $msg] = coordinator_confirm($u, $_POST);
    else [$ok, $msg] = submit_delivery($u, $_POST, $_FILES['photos'] ?? []);
    flash($ok ? 'success' : 'error', $msg);
    redirect('dashboard/confirm.php');
}

if ($role === 'coordinator') {
    $ms = rows("SELECT m.*, z.name zone, n.organization ngo, v.plate, t.name driver FROM relief_missions m LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN vehicles v ON v.id=m.vehicle_id LEFT JOIN team_members t ON t.id=m.driver_id WHERE m.coordinator_id=? AND m.status IN ('in_transit','arrived') ORDER BY m.status='arrived' DESC, m.dispatched_at DESC", [$id]);
    $recent = rows("SELECT m.code, m.title, m.coord_families, m.coord_confirmed_at FROM relief_missions m WHERE m.coordinator_id=? AND m.coord_confirmed=1 ORDER BY m.coord_confirmed_at DESC LIMIT 5", [$id]);
    dash_head('Delivery Confirmation', 'confirm', 'Confirm what actually reached your zone');
    ?>
    <div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00" data-offline-banner hidden><span><b>You are offline</b><span class="sub">Confirmations are saved on this device and sent when you reconnect.</span></span></div>
    <?php foreach ($ms as $m): ?>
    <section class="panel"><header><div><h2><?= e($m['code']) ?> · <?= e($m['title']) ?></h2><span class="sub"><?= e($m['ngo']) ?> · <?= e($m['plate'] ?: 'no vehicle') ?> · driver <?= e($m['driver'] ?: '—') ?></span></div><?= badge($m['status']) ?></header>
      <div class="cols c11" style="padding:1.2rem 1.3rem"><div class="code-card"><small>Mission confirmation code</small><div class="code-box"><?php foreach (str_split((string)$m['confirmation_code']) as $ch) echo '<span>' . e($ch) . '</span>'; ?></div><small>Give this code to the NGO team leader <b>only after the goods have arrived</b>. They need it to submit their evidence.</small></div>
        <div class="kv one" style="padding:0"><div><span>Cargo</span><b><?= e($m['cargo'] ?: number_format($m['target_qty']) . ' ' . $m['unit']) ?></b></div><div><span>Zone</span><b><?= e($m['zone']) ?></b></div><div><span>ETA</span><b><?= e(dt_label($m['eta'])) ?></b></div><div><span>Your confirmation</span><b><?= $m['coord_confirmed'] ? '✓ ' . number_format($m['coord_families']) . ' families' : 'Not yet' ?></b></div></div></div>
      <?php if (!$m['coord_confirmed']): ?><form method="post" class="pad" style="border-top:1px solid var(--line)" data-offline-type="confirm" data-offline-label="Delivery confirmation <?= e($m['code']) ?>"><?= csrf_field() ?><input type="hidden" name="client_uid" value=""><input type="hidden" name="mission_id" value="<?= (int)$m['id'] ?>">
        <div class="field" style="max-width:360px"><label for="f<?= (int)$m['id'] ?>">Actual families served *</label><input type="number" id="f<?= (int)$m['id'] ?>" name="families" min="1" required placeholder="Count of households who received relief"></div>
        <div class="field"><label>Delivery notes</label><input type="text" name="notes" maxlength="300" placeholder="Any issues, unserved households, follow-up needs…"></div>
        <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="agree" value="1"> I confirm this delivery information is accurate and that receipts were distributed to the households.</label>
        <button class="btn btn-primary">Confirm delivery</button></form><?php else: ?><div class="pad" style="border-top:1px solid var(--line);color:var(--muted)">You confirmed this delivery <?= ago($m['coord_confirmed_at']) ?>. The NGO team can now submit evidence for audit.</div><?php endif; ?></section>
    <?php endforeach; if (!$ms): ?><div class="panel"><div class="empty">No relief is on the way to your zone right now.</div></div><?php endif; ?>
    <?php if ($recent): ?><section class="panel"><header><h2>Recently confirmed</h2></header><?php foreach ($recent as $r): ?><div class="list-row"><span><code><?= e($r['code']) ?></code> <b><?= e($r['title']) ?></b><small><?= number_format($r['coord_families']) ?> families · <?= ago($r['coord_confirmed_at']) ?></small></span><span class="badge b-green">Confirmed</span></div><?php endforeach; ?></section><?php endif;
    dash_foot(); exit;
}

$ms = rows("SELECT m.*, z.name zone FROM relief_missions m LEFT JOIN zones z ON z.id=m.zone_id WHERE m.ngo_id=? AND m.status IN ('in_transit','arrived') ORDER BY m.status='arrived' DESC, m.dispatched_at DESC", [$id]);
$mine = rows("SELECT dl.*, m.code, m.title, m.unit, (SELECT COUNT(*) FROM delivery_photos p WHERE p.delivery_id=dl.id) photos FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id WHERE m.ngo_id=? ORDER BY dl.created_at DESC LIMIT 12", [$id]);
$sel = (int)($_GET['mission'] ?? ($ms[0]['id'] ?? 0));
dash_head('Delivery Confirmation', 'confirm', 'Submit evidence once the goods have arrived');
?>
<div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00" data-offline-banner hidden><span><b>You are offline</b><span class="sub">Photo evidence needs a connection. Reconnect to submit.</span></span></div>
<?php if (!$ms): ?><div class="panel"><div class="empty">No missions are on the road. Dispatch one first, then confirm here when it arrives.</div></div><?php else: ?>
<section class="panel"><header><h2>Submit delivery evidence</h2></header>
<form method="post" enctype="multipart/form-data" class="pad"><?= csrf_field() ?><span data-needs-photos hidden></span>
  <div class="two"><div class="field"><label for="mission_id">Mission</label><select id="mission_id" name="mission_id" onchange="location='?mission='+this.value"><?php foreach ($ms as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === $sel ? 'selected' : '' ?>><?= e($m['code'] . ' · ' . $m['title']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="code">Coordinator confirmation code *</label><input type="text" id="code" name="code" class="code-in" inputmode="numeric" maxlength="6" pattern="\d{6}" data-digits required placeholder="••••••" autocomplete="off"><div class="hint">The Spot Coordinator gives you this 6-digit code when the goods arrive.</div></div></div>
  <div class="three"><div class="field"><label for="quantity">Quantity delivered *</label><input type="number" id="quantity" name="quantity" min="1" required></div><div class="field"><label for="recipients">Families served *</label><input type="number" id="recipients" name="recipients" min="1" required></div><div class="field"><label for="location">Delivery location</label><input type="text" id="location" name="location" maxlength="200" placeholder="Union, village or landmark"></div></div>
  <div class="field"><label>GPS location of the delivery point *</label><div class="geo-row"><input type="text" id="gps_lat" name="gps_lat" placeholder="Latitude" required><input type="text" id="gps_lng" name="gps_lng" placeholder="Longitude" required><button type="button" class="btn btn-outline" data-geo="gps_lat,gps_lng,geo_state"><?= icon('pin') ?>Detect location</button></div><div class="geo-state" id="geo_state">The audit team compares this with the zone. The timestamp is recorded automatically.</div></div>
  <div class="field"><label for="photos">Delivery photos * (3 to 8)</label><input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-photo-input="photo_state"><div class="geo-state" id="photo_state">Minimum 3 photos required for audit. JPG, PNG or WebP, up to 6 MB each.</div></div>
  <div class="field"><label for="notes">Delivery notes</label><textarea id="notes" name="notes" maxlength="500" placeholder="Any issues, partial deliveries, follow-up needs…"></textarea></div>
  <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="agree" value="1"> I confirm the information above is accurate and receipts were distributed to the households.</label>
  <button class="btn btn-green" type="submit">Submit delivery report</button></form></section><?php endif; ?>
<section class="panel"><header><h2>Your recent submissions</h2></header><table class="tbl"><thead><tr><th>Mission</th><th class="num">Quantity</th><th class="num">Families</th><th>Evidence</th><th>Status</th></tr></thead><tbody>
<?php foreach ($mine as $d): ?><tr><td><code><?= e($d['code']) ?></code><br><b><?= e($d['title']) ?></b><small><?= e(dt_label($d['created_at'])) ?></small></td><td class="num"><?= number_format($d['quantity']) ?> <small style="display:inline"><?= e($d['unit']) ?></small></td><td class="num"><?= number_format($d['recipients']) ?></td><td><span class="ev ok"><?= icon('camera') ?><?= (int)$d['photos'] ?> photos</span></td><td><?= badge($d['status']) ?><?php if ($d['review_note']): ?><small>“<?= e($d['review_note']) ?>”</small><?php endif; ?></td></tr>
<?php endforeach; if (!$mine) echo empty_row(5, 'Nothing submitted yet.'); ?></tbody></table></section>
<?php dash_foot();
