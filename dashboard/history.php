<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['audit', 'super_admin']);
$qs = trim((string)($_GET['q'] ?? '')); $st = $_GET['status'] ?? 'all'; $w = ''; $p = [];
if (in_array($st, ['verified', 'rejected'], true)) { $w .= ' AND dl.status=?'; $p[] = $st; } else $st = 'all';
if ($qs !== '') { $w .= ' AND (m.code LIKE ? OR n.organization LIKE ? OR dl.location LIKE ? OR m.title LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
$list = rows("SELECT dl.*, m.code, m.title, m.unit, m.dispatched_qty, n.organization, rv.first_name vf, rv.last_name vl FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN users rv ON rv.id=dl.reviewed_by WHERE dl.status<>'pending'$w ORDER BY dl.reviewed_at DESC LIMIT 200", $p);
dash_head('Audit History', 'history', count($list) . ' reviewed deliveries');
?>
<form class="filter-bar" method="get"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search NGO, mission ID or place"><select name="status" onchange="this.form.submit()"><option value="all">All decisions</option><option value="verified" <?= $st === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= $st === 'rejected' ? 'selected' : '' ?>>Rejected</option></select><button class="btn btn-outline btn-sm">Search</button></form>
<section class="panel"><header><h2>Audit History</h2></header>
<?php foreach ($list as $d): $diff = $d['dispatched_qty'] !== null && $d['status'] === 'verified' ? 0 : 0; ?>
  <a class="list-row" href="<?= url('dashboard/review.php?id=' . (int)$d['id']) ?>" style="color:var(--ink);text-decoration:none;align-items:flex-start"><span><code><?= e($d['code']) ?></code><br><b><?= e($d['organization']) ?>: <?= e($d['title']) ?></b><small><?= e($d['location']) ?> · <?= number_format($d['quantity']) ?> <?= e($d['unit']) ?> · <?= number_format($d['recipients']) ?> families</small><small>Reviewed <?= e(dt_label($d['reviewed_at'])) ?> by <?= e(trim($d['vf'] . ' ' . $d['vl'])) ?></small>
    <?php if ($d['review_note']): ?><small style="color:<?= $d['status'] === 'rejected' ? 'var(--red-d)' : 'var(--muted)' ?>;margin-top:.3rem">“<?= e($d['review_note']) ?>”</small><?php endif; ?></span><?= badge($d['status']) ?></a>
<?php endforeach; if (!$list) echo '<div class="empty">No reviewed deliveries match.</div>'; ?></section>
<?php dash_foot();
