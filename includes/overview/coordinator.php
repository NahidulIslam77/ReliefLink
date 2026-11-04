<?php
$zone = $u['zone_id'] ? row('SELECT z.*, di.name district, d.title dtitle FROM zones z JOIN districts di ON di.id=z.district_id LEFT JOIN disasters d ON d.id=z.disaster_id WHERE z.id=?', [(int)$u['zone_id']]) : null;
[$ms, $mp] = mission_scope($u);
$act = (int)val("SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE m.status IN ('accepted','in_transit','arrived')$ms", $mp);
$conf = (int)val("SELECT COUNT(*) FROM relief_missions WHERE coordinator_id=? AND status IN ('in_transit','arrived') AND coord_confirmed=0", [(int)$u['id']]);
$pend = $zone ? (int)val("SELECT COUNT(*) FROM demands WHERE zone_id=? AND status='pending'", [(int)$zone['id']]) : 0;
$dem = rows("SELECT * FROM demands WHERE reported_by=? ORDER BY created_at DESC LIMIT 5", [(int)$u['id']]);
dash_head('Dashboard', 'index', $zone ? $zone['name'] : $u['district_name']);
?>
<div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00" data-offline-banner hidden><span><b>Offline mode active</b><span class="sub" data-offline-count>Items are saved on this device.</span></span><button class="btn btn-sm" style="background:#F2C94C" data-sync-now type="button">Sync now</button></div>
<section class="panel"><div class="pad"><?php if ($zone): ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start"><div><small style="color:var(--blue);font-weight:700;letter-spacing:.04em">YOUR ZONE</small><h2 style="font-size:1.3rem;margin:.2rem 0"><?= e($zone['name']) ?></h2><span style="color:var(--blue)"><?= e($zone['district']) ?> District · <?= e($zone['dtitle'] ?: 'No disaster linked') ?></span></div><?= badge($zone['severity']) ?></div>
  <div class="stat3" style="padding:1.2rem 0 0"><div><b><?= number_format($zone['households']) ?></b>Households</div><div><b><?= number_format($zone['affected_people']) ?></b>Affected</div><div><b><?= $pend ?></b>Pending demands</div></div>
<?php else: ?><div class="empty">No zone is assigned to your account yet. Ask your District Admin or the National Admin to set your zone.</div><?php endif; ?></div></section>
<div class="cols c11"><a href="<?= url('dashboard/demand-new.php') ?>" class="panel" style="background:#1E4FD6;color:#fff;text-decoration:none;padding:1.6rem"><span class="tile-ic" style="display:block"><?= icon('plus') ?></span><b style="display:block;font-size:1.2rem;margin:.6rem 0 .2rem">Submit Demand</b><span style="opacity:.85">Report food, water, medicine or shelter needs</span></a>
<a href="<?= url('dashboard/sos.php') ?>" class="panel" style="background:#E5322D;color:#fff;text-decoration:none;padding:1.6rem"><span class="tile-ic" style="display:block"><?= icon('alert') ?></span><b style="display:block;font-size:1.2rem;margin:.6rem 0 .2rem">Emergency SOS</b><span style="opacity:.9">Trigger an urgent emergency alert</span></a></div>
<div class="cols c11"><a class="panel pad" href="<?= url('dashboard/missions.php') ?>" style="color:var(--ink);text-decoration:none"><span class="tile-ic" style="color:var(--blue)"><?= icon('zap') ?></span><h3>Active Missions</h3><b style="font-size:1.8rem;color:var(--blue)"><?= $act ?></b><br><small style="color:var(--muted)">En route to your zone</small></a>
<a class="panel pad" href="<?= url('dashboard/confirm.php') ?>" style="color:var(--ink);text-decoration:none"><span class="tile-ic" style="color:var(--green)"><?= icon('check') ?></span><h3>Confirm Delivery</h3><b style="font-size:1.8rem;color:var(--green)"><?= $conf ?></b><br><small style="color:var(--muted)">Awaiting your confirmation</small></a></div>
<section class="panel"><header><h2>Recent Demands</h2><a href="<?= url('dashboard/demands.php') ?>" style="font-size:13px">All history →</a></header>
<?php foreach ($dem as $d): ?><div class="list-row"><span><i class="sev-dot sev-<?= e($d['priority']) ?>"></i><b><?= e(ucfirst($d['item_type'])) ?> · <?= number_format($d['quantity']) ?> <?= e($d['unit']) ?></b><small><?= e(date('M j · H:i', strtotime($d['created_at']))) ?></small></span><?= badge($d['status']) ?></div><?php endforeach; if (!$dem): ?><div class="empty">You have not reported any demands yet.</div><?php endif; ?></section>
<?php dash_foot();
