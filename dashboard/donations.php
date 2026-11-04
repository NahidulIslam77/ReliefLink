<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'audit']);

if (is_post()) {
    csrf_check();
    $a = post('action'); $id = (int)post('id');
    $d = row('SELECT * FROM donations WHERE id=?', [$id]);
    if (!$d || $d['status'] !== 'pending') flash('error', 'This donation has already been reviewed.');
    elseif (in_array($a, ['confirm', 'reject'], true)) {
        q('UPDATE donations SET status=?, confirmed_by=? WHERE id=? AND status="pending"', [$a === 'confirm' ? 'confirmed' : 'rejected', (int)$u['id'], $id]);
        audit('donation.' . $a, 'donation', $id, money($d['amount']) . ' via ' . $d['method'] . ' · ' . $d['trx_ref']);
        flash('success', $a === 'confirm' ? 'Donation confirmed. It now shows in the public totals.' : 'Donation rejected.');
    }
    redirect('dashboard/donations.php');
}

$st = $_GET['status'] ?? 'pending'; $w = ''; $p = [];
if (in_array($st, ['pending', 'confirmed', 'rejected'], true)) { $w = ' WHERE n.status=?'; $p[] = $st; } else $st = 'all';
$list = rows("SELECT n.*, d.title AS dtitle, di.name AS district FROM donations n LEFT JOIN disasters d ON d.id=n.disaster_id LEFT JOIN districts di ON di.id=d.district_id$w ORDER BY n.status='pending' DESC, n.created_at DESC LIMIT 200", $p);
$t = row("SELECT COALESCE(SUM(CASE WHEN status='confirmed' THEN amount END),0) conf, COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0) pend, SUM(status='pending') pn, SUM(status='confirmed') cn FROM donations");
$by = rows("SELECT COALESCE(CONCAT(di.name,' · ',d.title),'General fund') label, SUM(n.amount) amt FROM donations n LEFT JOIN disasters d ON d.id=n.disaster_id LEFT JOIN districts di ON di.id=d.district_id WHERE n.status='confirmed' GROUP BY label ORDER BY amt DESC LIMIT 8");

dash_head('Donations', 'donations', 'Match each transaction ID with your payment records before confirming');
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi green"><div><small>Confirmed</small><b><?= money($t['conf']) ?></b><span><?= (int)$t['cn'] ?> donations</span></div><span class="ic g">✅</span></div>
  <div class="kpi amber"><div><small>Pending review</small><b><?= money($t['pend']) ?></b><span><?= (int)$t['pn'] ?> donations</span></div><span class="ic a">⏳</span></div>
  <div class="kpi"><div><small>Showing</small><b><?= count($list) ?></b><span><?= e($st) ?></span></div><span class="ic b">☰</span></div>
</div>
<div class="cols c21">
<div><?php tabs(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'all' => 'All'], $st); ?>
<section class="panel" style="margin-top:.8rem"><table class="tbl"><thead><tr><th>Donor</th><th class="num">Amount</th><th>Payment</th><th>For</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
<?php foreach ($list as $d): ?>
  <tr><td><b><?= e($d['donor_name']) ?></b><small><?= e($d['email'] ?: 'no email') ?> · <?= ago($d['created_at']) ?></small></td><td class="num"><b><?= money($d['amount']) ?></b></td>
    <td><?= e(ucfirst($d['method'])) ?><small>TrxID <code><?= e($d['trx_ref']) ?></code></small></td>
    <td><?= $d['dtitle'] ? e($d['district']) . '<small>' . e($d['dtitle']) . '</small>' : '<small>General fund</small>' ?></td><td><?= badge($d['status']) ?></td>
    <td><?php if ($d['status'] === 'pending'): ?><form method="post" class="actions" style="display:flex"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-green btn-xs" name="action" value="confirm">Confirm</button><button class="btn btn-outline btn-xs" name="action" value="reject" style="color:var(--red-d)">Reject</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="empty">No donations in this view.</td></tr><?php endif; ?>
</tbody></table></section></div>
<section class="panel"><header><h2>Confirmed by destination</h2></header>
  <?php foreach ($by as $b): ?><div class="list-row"><span><?= e($b['label']) ?></span><b><?= money($b['amt']) ?></b></div><?php endforeach; if (!$by): ?><div class="empty">Nothing confirmed yet.</div><?php endif; ?></section>
</div>
<?php dash_foot();
