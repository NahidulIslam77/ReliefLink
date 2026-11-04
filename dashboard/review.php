<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['audit', 'super_admin']);
$id = (int)($_GET['id'] ?? post('id'));
$d = row("SELECT dl.*, m.code, m.title, m.unit, m.priority, m.target_qty, m.delivered_qty, m.dispatched_qty, m.dispatched_at, m.eta, m.coord_confirmed, m.coord_families, m.coord_notes, m.coord_confirmed_at, m.demand_id,
  n.organization, z.name zone, z.lat zlat, z.lng zlng, dm.households, dm.code dcode, rp.first_name rf, rp.last_name rl, cu.first_name cf, cu.last_name cl, rv.first_name vf, rv.last_name vl
  FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN demands dm ON dm.id=m.demand_id LEFT JOIN users rp ON rp.id=dl.reported_by LEFT JOIN users cu ON cu.id=m.coordinator_id LEFT JOIN users rv ON rv.id=dl.reviewed_by WHERE dl.id=?", [$id]);
if (!$d) { flash('error', 'Delivery not found.'); redirect('dashboard/deliveries.php'); }
$photos = array_column(rows('SELECT path FROM delivery_photos WHERE delivery_id=? ORDER BY id', [$id]), 'path');
$dist = ($d['gps_lat'] !== null && $d['zlat'] !== null) ? haversine((float)$d['gps_lat'], (float)$d['gps_lng'], (float)$d['zlat'], (float)$d['zlng']) : null;
$auto = ['photos' => count($photos) >= 3, 'gps' => $dist !== null && $dist <= EVIDENCE_RADIUS_KM, 'sig' => (bool)$d['coord_confirmed'],
         'qty' => $d['dispatched_qty'] === null || (int)$d['quantity'] <= (int)$d['dispatched_qty'],
         'time' => !$d['evidence_at'] || !$d['dispatched_at'] || (strtotime($d['evidence_at']) >= strtotime($d['dispatched_at']) - 600 && (!$d['eta'] || strtotime($d['evidence_at']) <= strtotime($d['eta']) + 3 * 3600))];
$REQ = ['c_photos', 'c_gps', 'c_families', 'c_signature', 'c_quantity'];

if (is_post() && $d['status'] !== 'pending') { csrf_check(); flash('error', 'This delivery has already been reviewed.'); redirect('dashboard/review.php?id=' . $id); }
if (is_post()) {
    csrf_check(); $dec = post('decision'); $note = mb_substr(post('note'), 0, 300); $checks = (array)($_POST['checks'] ?? []);
    if (!in_array($dec, ['verify', 'reject'], true)) flash('error', 'Choose Verify or Reject.');
    elseif ($dec === 'reject' && $note === '') flash('error', 'Add the reason for rejecting so the team knows what to fix.');
    elseif ($dec === 'verify' && array_diff($REQ, $checks)) flash('error', 'Complete every required check (marked *) before verifying.');
    elseif ($dec === 'verify' && !$auto['photos']) flash('error', 'At least 3 photos are required. Reject this report so the team can resubmit.');
    else { [$ok, $msg] = review_delivery($id, $dec, $u, $note); flash($ok ? 'success' : 'error', $msg); if ($ok) redirect('dashboard/deliveries.php'); }
    redirect('dashboard/review.php?id=' . $id);
}
$fmt = fn($ok) => '<span class="ev ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'Looks fine' : 'Check this') . '</span>';
dash_head('Review Delivery', 'deliveries', $d['code']);
?>
<div class="page-actions"><a href="<?= url('dashboard/deliveries.php') ?>" style="font-size:14px">← Back to the queue</a><span><?= badge($d['priority']) ?> <?= badge($d['status']) ?></span></div>
<div class="alert blue"><span><b>Verification checklist for <?= e($d['code']) ?></b><span class="sub"><?= e($d['organization']) ?> · <?= e($d['title']) ?> · submitted <?= e(dt_label($d['created_at'])) ?> by <?= e(trim($d['rf'] . ' ' . $d['rl']) ?: '—') ?></span></span></div>
<div class="cols c32"><section class="panel"><header><h2>Photo evidence</h2><span class="ev <?= $auto['photos'] ? 'ok' : 'bad' ?>"><?= count($photos) ?> photos</span></header>
  <?php if ($photos): ?><div class="photo-big"><img id="big" src="<?= e(url($photos[0])) ?>" alt="Delivery evidence photo 1"></div><div class="photo-grid"><?php foreach ($photos as $i => $ph): ?><a href="<?= e(url($ph)) ?>" target="_blank" rel="noopener" onclick="document.getElementById('big').src=this.href;return false" title="Photo <?= $i + 1 ?>"><img src="<?= e(url($ph)) ?>" alt="Evidence photo <?= $i + 1 ?>" loading="lazy"></a><?php endforeach; ?></div><?php else: ?><div class="empty">No photos were attached.</div><?php endif; ?></section>
  <div style="display:grid;gap:1.2rem;align-content:start"><section class="panel"><header><h2>GPS verification</h2><?= $fmt($auto['gps']) ?></header>
    <?php if ($d['gps_lat'] !== null): ?><div class="mapbox route" style="height:190px" data-route='<?= e(json_encode(['from' => ['lat' => $d['zlat'], 'lng' => $d['zlng'], 'label' => 'Zone centre'], 'to' => ['lat' => $d['gps_lat'], 'lng' => $d['gps_lng'], 'label' => 'Evidence point'], 'progress' => 100])) ?>'></div><?php endif; ?>
    <div class="kv one"><div><span>Evidence point</span><b><?= $d['gps_lat'] !== null ? e(number_format((float)$d['gps_lat'], 4) . '° N, ' . number_format((float)$d['gps_lng'], 4) . '° E') : '—' ?></b></div><div><span>Distance from <?= e($d['zone']) ?> centre</span><b><?= $dist !== null ? round($dist, 1) . ' km' : '—' ?> <small>(limit <?= EVIDENCE_RADIUS_KM ?> km)</small></b></div><div><span>Timestamp</span><b><?= e(dt_label($d['evidence_at'])) ?></b></div></div></section>
  <section class="panel"><header><h2>Records to compare</h2></header><div class="kv one"><div><span>Quantity delivered</span><b><?= number_format($d['quantity']) ?> <?= e($d['unit']) ?></b></div><div><span>Dispatch record</span><b><?= $d['dispatched_qty'] !== null ? number_format($d['dispatched_qty']) . ' ' . e($d['unit']) : '—' ?></b></div><div><span>Mission target / verified so far</span><b><?= number_format($d['target_qty']) ?> / <?= number_format($d['delivered_qty']) ?></b></div><div><span>Families (NGO report)</span><b><?= number_format($d['recipients']) ?></b></div><div><span>Families (coordinator)</span><b><?= $d['coord_confirmed'] ? number_format($d['coord_families']) : 'not confirmed' ?></b></div><div><span>Demand record</span><b><?= $d['households'] ? (int)$d['households'] . ' households (' . e($d['dcode']) . ')' : 'Bulk mission' ?></b></div><div><span>Coordinator</span><b><?= e(trim($d['cf'] . ' ' . $d['cl']) ?: '—') ?><?= $d['coord_confirmed'] ? ' ✓' : '' ?></b></div></div>
    <?php if ($d['notes'] || $d['coord_notes']): ?><p style="padding:0 1.3rem 1rem;color:var(--muted);font-size:13.5px"><?= $d['notes'] ? 'NGO: “' . e($d['notes']) . '” ' : '' ?><?= $d['coord_notes'] ? '<br>Coordinator: “' . e($d['coord_notes']) . '”' : '' ?></p><?php endif; ?></section></div></div>
<?php if ($d['status'] === 'pending'): ?>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<section class="panel"><header><h2>Audit checklist</h2><span class="sub">Complete the required checks (<span style="color:var(--red)">*</span>) before verifying</span></header>
  <div class="checklist"><?php foreach ([['c_photos', 'Photo evidence reviewed (min. 3 photos required)', 1, 'photos'], ['c_gps', 'GPS coordinates match the declared delivery zone', 1, 'gps'], ['c_families', 'Family count verified against demand record', 1, null], ['c_signature', 'Spot Coordinator digital signature present', 1, 'sig'], ['c_quantity', 'Quantity delivered matches dispatch record', 1, 'qty'], ['c_time', 'Delivery time is within the mission window (±3 hours of ETA)', 0, 'time'], ['c_condition', 'Relief goods are in good condition (photographic confirmation)', 0, null], ['c_complaints', 'No prior complaint or SOS alert related to this delivery', 0, null]] as [$n, $l, $r, $a]): ?>
    <label><input type="checkbox" name="checks[]" value="<?= $n ?>"><span><?= e($l) ?> <?= $r ? '<span class="req">*</span>' : '' ?></span><?php if ($a): ?><span class="auto"><?= $fmt($auto[$a]) ?></span><?php endif; ?></label><?php endforeach; ?></div></section>
<section class="panel"><header><h2>Decision</h2></header><div class="decision" style="padding-top:1.2rem"><label><input type="radio" name="decision" value="verify" required><span>✓ Verify delivery</span></label><label><input type="radio" name="decision" value="reject"><span>✕ Reject delivery</span></label></div>
  <div class="pad"><div class="field"><label for="note">Auditor notes <span class="hint" style="display:inline">(required to reject)</span></label><textarea id="note" name="note" maxlength="300" placeholder="What did you check? What needs to change?"></textarea></div><button class="btn btn-primary" type="submit">Confirm decision</button></div></section></form>
<?php else: ?><section class="panel"><header><h2>Decision</h2><?= badge($d['status']) ?></header><div class="pad">Reviewed by <b><?= e(trim($d['vf'] . ' ' . $d['vl'])) ?></b> on <?= e(dt_label($d['reviewed_at'])) ?>.<?php if ($d['review_note']): ?><p style="margin-top:.5rem;color:var(--muted)">“<?= e($d['review_note']) ?>”</p><?php endif; ?></div></section><?php endif;
dash_foot();
