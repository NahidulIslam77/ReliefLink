<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['audit', 'super_admin']);
$days = (int)($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
$per = "dl.reviewed_at >= NOW() - INTERVAL $days DAY AND dl.status<>'pending'";
$k = row("SELECT COUNT(*) n, SUM(dl.status='verified') v, SUM(dl.status='rejected') r, COALESCE(SUM(CASE WHEN dl.status='verified' THEN dl.quantity END),0) units, COALESCE(SUM(CASE WHEN dl.status='verified' THEN dl.recipients END),0) ppl, AVG(TIMESTAMPDIFF(MINUTE, dl.created_at, dl.reviewed_at)) mins FROM deliveries dl WHERE $per");
$byNgo = rows("SELECT n.organization org, COUNT(*) n, SUM(dl.status='verified') v, SUM(dl.status='rejected') r, COALESCE(SUM(CASE WHEN dl.status='verified' THEN dl.quantity END),0) units FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id WHERE $per GROUP BY n.organization ORDER BY n DESC");
$byDist = rows("SELECT di.name dist, COUNT(*) n, SUM(dl.status='verified') v, SUM(dl.status='rejected') r, COALESCE(SUM(CASE WHEN dl.status='verified' THEN dl.recipients END),0) ppl FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id JOIN disasters d ON d.id=m.disaster_id JOIN districts di ON di.id=d.district_id WHERE $per GROUP BY di.name ORDER BY n DESC");
$reasons = rows("SELECT dl.review_note, m.code, n.organization FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id WHERE dl.status='rejected' AND $per ORDER BY dl.reviewed_at DESC LIMIT 10");
if (($_GET['export'] ?? '') === 'csv') {
    audit('report.export', 'report', null, "Audit report, last $days days");
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="relieflink-audit-report-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Delivery ID', 'Mission', 'NGO', 'Zone', 'Quantity', 'Unit', 'Families', 'Status', 'Submitted', 'Reviewed', 'Reviewer', 'Note']);
    foreach (rows("SELECT dl.id, m.code, n.organization, z.name zone, dl.quantity, m.unit, dl.recipients, dl.status, dl.created_at, dl.reviewed_at, CONCAT(rv.first_name,' ',rv.last_name) rev, dl.review_note FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id LEFT JOIN users n ON n.id=m.ngo_id LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN users rv ON rv.id=dl.reviewed_by WHERE $per ORDER BY dl.reviewed_at DESC") as $r) fputcsv($o, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, array_values($r)));
    exit;
}
$rate = ($k['v'] + $k['r']) ? round($k['v'] / ($k['v'] + $k['r']) * 100) : 100;
dash_head('Audit Report', 'report', 'Verification summary · last ' . $days . ' days');
?>
<div class="page-actions"><form method="get" class="toolbar"><select name="days" onchange="this.form.submit()" aria-label="Period"><?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $l) echo '<option value="' . $d . '"' . ($days === $d ? ' selected' : '') . '>' . $l . '</option>'; ?></select></form>
  <span class="toolbar"><a class="btn btn-outline btn-sm" href="?days=<?= $days ?>&export=csv">Export CSV</a><button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Print report</button></span></div>
<div class="kpis"><?= kpi('', 'Deliveries reviewed', (int)$k['n'], (int)$k['v'] . ' verified · ' . (int)$k['r'] . ' rejected', 'clipboard', 'b') . kpi('green', 'Pass rate', $rate . '%', 'Verified of reviewed', 'check', 'g') . kpi('blue', 'Verified units', (int)$k['units'], number_format($k['ppl']) . ' people reached', 'box', 'b') . kpi('amber', 'Avg review time', $k['mins'] !== null ? ($k['mins'] < 120 ? round($k['mins']) . ' min' : round($k['mins'] / 60, 1) . ' h') : '—', 'Submission to decision', 'clock', 'a') ?></div>
<div class="cols c11"><section class="panel"><header><h2>By NGO / team</h2></header><table class="tbl"><thead><tr><th>Team</th><th class="num">Reviewed</th><th class="num">Verified</th><th class="num">Rejected</th><th class="num">Pass rate</th><th class="num">Units</th></tr></thead><tbody>
<?php foreach ($byNgo as $r): ?><tr><td><b><?= e($r['org'] ?: '—') ?></b></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= (int)$r['v'] ?></td><td class="num"><?= (int)$r['r'] ?></td><td class="num"><?= pct($r['v'], $r['n']) ?>%</td><td class="num"><?= number_format($r['units']) ?></td></tr><?php endforeach; if (!$byNgo) echo empty_row(6, 'No reviews in this period.'); ?></tbody></table></section>
<section class="panel"><header><h2>By district</h2></header><table class="tbl"><thead><tr><th>District</th><th class="num">Reviewed</th><th class="num">Verified</th><th class="num">Rejected</th><th class="num">People reached</th></tr></thead><tbody>
<?php foreach ($byDist as $r): ?><tr><td><b><?= e($r['dist']) ?></b></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= (int)$r['v'] ?></td><td class="num"><?= (int)$r['r'] ?></td><td class="num"><?= number_format($r['ppl']) ?></td></tr><?php endforeach; if (!$byDist) echo empty_row(5, 'No reviews in this period.'); ?></tbody></table></section></div>
<section class="panel"><header><h2>Recent rejection reasons</h2></header><?php foreach ($reasons as $r): ?><div class="list-row"><span><code><?= e($r['code']) ?></code> <b><?= e($r['organization']) ?></b><small><?= e($r['review_note']) ?></small></span><?= badge('rejected') ?></div><?php endforeach; if (!$reasons) echo '<div class="empty">No rejections in this period.</div>'; ?></section>
<?php dash_foot();
