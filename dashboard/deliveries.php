<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['audit', 'super_admin']);
$st = $_GET['status'] ?? 'pending'; $pr = $_GET['priority'] ?? '';
$w = ''; $p = [];
if (in_array($st, ['pending', 'verified', 'rejected'], true)) { $w .= ' AND dl.status=?'; $p[] = $st; } else $st = 'all';
if (in_array($pr, ['critical', 'high', 'medium', 'low'], true)) { $w .= ' AND m.priority=?'; $p[] = $pr; } else $pr = '';
$list = rows("SELECT dl.*, m.code, m.title, m.unit, m.priority, m.coord_confirmed, n.organization, z.name zone, z.lat zlat, z.lng zlng, (SELECT COUNT(*) FROM delivery_photos x WHERE x.delivery_id=dl.id) photos
  FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN zones z ON z.id=m.zone_id WHERE 1=1$w
  ORDER BY dl.status='pending' DESC, FIELD(m.priority,'critical','high','medium','low'), dl.created_at LIMIT 200", $p);
$cnt = ['pending' => 0, 'verified' => 0, 'rejected' => 0]; foreach (rows('SELECT status, COUNT(*) c FROM deliveries GROUP BY status') as $r) $cnt[$r['status']] = (int)$r['c'];
dash_head($u['role'] === 'audit' ? 'Pending Verification' : 'Deliveries', 'deliveries', 'Only verified quantities count toward mission totals');
?>
<div class="filter-bar"><?php tabs(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'], $st, 'status', $cnt); ?>
  <form method="get" style="margin-left:auto"><input type="hidden" name="status" value="<?= e($st) ?>"><select name="priority" onchange="this.form.submit()" aria-label="Priority"><option value="">All priorities</option><?= opts(['critical', 'high', 'medium'], $pr) ?></select></form></div>
<div class="card-list"><?php foreach ($list as $d): $dist = ($d['gps_lat'] !== null && $d['zlat'] !== null) ? haversine((float)$d['gps_lat'], (float)$d['gps_lng'], (float)$d['zlat'], (float)$d['zlng']) : null; ?>
  <a class="dl-card" href="<?= url('dashboard/review.php?id=' . (int)$d['id']) ?>"><span style="flex:1"><span class="row"><span class="code"><?= e($d['code']) ?></span><?= badge($d['priority']) ?><?= badge($d['status']) ?></span>
    <h4><?= e($d['organization']) ?>: <?= e($d['title']) ?></h4><small><?= number_format($d['quantity']) ?> <?= e($d['unit']) ?> · <?= number_format($d['recipients']) ?> families · <?= e($d['zone']) ?> · delivered <?= e(dt_label($d['evidence_at'] ?: $d['created_at'])) ?></small>
    <div class="chips" style="margin-top:.5rem"><span class="ev <?= $d['photos'] >= 3 ? 'ok' : 'bad' ?>"><?= icon('camera') ?><?= (int)$d['photos'] ?> photos</span><span class="ev <?= $dist !== null && $dist <= EVIDENCE_RADIUS_KM ? 'ok' : 'bad' ?>"><?= icon('pin') ?><?= $dist === null ? 'No GPS' : ($dist <= EVIDENCE_RADIUS_KM ? 'GPS matches zone' : 'GPS ' . round($dist) . ' km off') ?></span><span class="ev <?= $d['coord_confirmed'] ? 'ok' : 'bad' ?>"><?= $d['coord_confirmed'] ? 'Coordinator signed' : 'No coordinator signature' ?></span></div></span>
    <span class="btn btn-outline btn-sm"><?= $d['status'] === 'pending' ? 'Review' : 'Open' ?></span></a>
<?php endforeach; ?></div>
<?php if (!$list): ?><div class="panel"><div class="empty"><?= $st === 'pending' ? 'The queue is clear. Nothing is waiting for verification.' : 'Nothing in this view.' ?></div></div><?php endif;
dash_foot();
