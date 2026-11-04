<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$errs = []; $done = isset($_GET['thanks']);
$sel = (int)($_POST['disaster_id'] ?? $_GET['disaster'] ?? 0);
if (is_post()) {
    csrf_check();
    $amount = (float)str_replace(',', '', post('amount'));
    $method = post('method'); $trx = post('trx_ref'); $name = post('donor_name'); $email = strtolower(post('email'));
    if ($amount < 10 || $amount > 100000000) $errs[] = 'Enter an amount of at least ৳10.';
    if (!isset(DONATION_ACCOUNTS[$method])) $errs[] = 'Choose a payment method.';
    if ($trx === '' || strlen($trx) > 60) $errs[] = 'Enter the transaction ID from your payment receipt.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email or leave it blank.';
    if ($sel && !val('SELECT COUNT(*) FROM disasters WHERE id=?', [$sel])) $sel = 0;
    if (!throttle('donate', 5, 600)) $errs[] = 'Too many submissions. Please try again shortly.';
    if (!$errs) {
        throttle_hit('donate');
        q('INSERT INTO donations (donor_name,email,amount,method,trx_ref,disaster_id,message) VALUES (?,?,?,?,?,?,?)',
          [isset($_POST['anon']) || $name === '' ? 'Anonymous' : $name, $email, $amount, $method, $trx, $sel ?: null, preg_replace('/^(.{0,255}).*$/su', '$1', post('message'))]);
        redirect('donate.php?thanks=1');
    }
}
$disasters = rows('SELECT d.id, d.title, di.name FROM disasters d JOIN districts di ON di.id=d.district_id WHERE d.status<>"resolved" ORDER BY d.declared_at DESC');
$total = (float)val('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status="confirmed"');
$recent = rows('SELECT donor_name, amount, created_at FROM donations WHERE status="confirmed" ORDER BY created_at DESC LIMIT 5');
public_head('Donate', 'donate'); ?>
<section class="page-hero"><div class="container"><span class="tag blue">Donate</span><h1>Fund verified relief</h1><p>Donations are recorded, confirmed by the finance team and linked to a disaster. <?= money($total) ?> confirmed so far.</p></div></section>
<section class="s" style="padding-top:2.5rem"><div class="container" style="max-width:920px">
<?php if ($done): ?>
  <div class="card" style="text-align:center"><div class="sos-big" style="background:var(--green);box-shadow:0 0 0 10px var(--green-l)">✓</div><h2>Thank you</h2>
    <p style="color:var(--muted);margin:.6rem 0 1.2rem">Your donation is recorded as <b>pending</b>. It becomes confirmed once the finance team matches your transaction ID.</p><a class="btn btn-outline" href="<?= url('donate.php') ?>">Donate again</a></div>
<?php else: ?>
  <div class="split" style="align-items:start;grid-template-columns:1.4fr 1fr;gap:1.5rem">
    <div class="card">
      <?php if ($errs): ?><div class="flash error" style="position:static;margin-bottom:1rem" role="alert"><span><?= implode('<br>', array_map('e', $errs)) ?></span></div><?php endif; ?>
      <form method="post"><?= csrf_field() ?>
        <div class="field"><label for="amount">Amount (BDT)</label>
          <div class="amounts"><?php foreach ([500, 1000, 5000, 10000] as $a): ?><button type="button" data-v="<?= $a ?>">৳<?= number_format($a) ?></button><?php endforeach; ?></div>
          <input type="number" id="amount" name="amount" min="10" step="1" required value="<?= old('amount') ?>"></div>
        <div class="field"><label>Payment method</label>
          <div class="pay"><?php foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'card' => 'Card', 'bank' => 'Bank'] as $k => $l): ?><label><input type="radio" name="method" value="<?= $k ?>" <?= ($_POST['method'] ?? '') === $k ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div>
          <ul class="hint" style="list-style:none"><?php foreach (DONATION_ACCOUNTS as $k => $v): ?><li><b><?= e(ucfirst($k)) ?>:</b> <?= e($v) ?></li><?php endforeach; ?></ul></div>
        <div class="field"><label for="trx_ref">Transaction ID</label><input type="text" id="trx_ref" name="trx_ref" required value="<?= old('trx_ref') ?>"><div class="hint">Send the money first, then enter the ID from your receipt.</div></div>
        <div class="field"><label for="disaster_id">Support a specific disaster (optional)</label><select id="disaster_id" name="disaster_id"><option value="">Wherever it is needed most</option><?php foreach ($disasters as $d): ?><option value="<?= $d['id'] ?>" <?= $sel === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name'] . ' · ' . $d['title']) ?></option><?php endforeach; ?></select></div>
        <div class="two"><div class="field"><label for="donor_name">Your name</label><input type="text" id="donor_name" name="donor_name" value="<?= old('donor_name') ?>"></div><div class="field"><label for="email">Email (for receipt)</label><input type="email" id="email" name="email" value="<?= old('email') ?>"></div></div>
        <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="anon" <?= isset($_POST['anon']) ? 'checked' : '' ?>> Show my donation as anonymous</label>
        <div class="field"><label for="message">Message (optional)</label><input type="text" id="message" name="message" maxlength="255" value="<?= old('message') ?>"></div>
        <button class="btn btn-primary btn-block" type="submit">Record my donation</button>
      </form>
    </div>
    <aside class="card"><h3 style="margin-bottom:.8rem">Recent confirmed donations</h3>
      <?php foreach ($recent as $r): ?><div class="row" style="display:flex;justify-content:space-between;padding:.55rem 0;border-bottom:1px solid var(--line);font-size:14px"><span><?= e($r['donor_name']) ?><br><small style="color:var(--muted)"><?= ago($r['created_at']) ?></small></span><b><?= money($r['amount']) ?></b></div><?php endforeach; ?>
      <?php if (!$recent): ?><p class="empty">Be the first to donate.</p><?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
</div></section>
<?php public_foot();
