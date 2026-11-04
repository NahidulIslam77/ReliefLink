<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$errs = []; $ref = (int)($_GET['ref'] ?? 0);
if (is_post()) {
    csrf_check();
    $f = ['name' => post('name'), 'phone' => preg_replace('/[\s-]/', '', post('phone')), 'district_id' => (int)post('district_id'), 'location' => post('location'),
          'people' => max(1, min(5000, (int)post('people_count', '1'))), 'need' => post('need_type'), 'desc' => post('description')];
    if (post('website') !== '') redirect('sos.php');            // honeypot for bots
    if ($f['name'] === '') $errs[] = 'Enter your name.';
    if (!preg_match('/^(?:\+?88)?(01[3-9]\d{8})$/', $f['phone'], $m)) $errs[] = 'Enter a valid mobile number (01XXXXXXXXX) so responders can call you.'; else $f['phone'] = $m[1];
    if (!$f['district_id']) $errs[] = 'Select your district.';
    if ($f['location'] === '') $errs[] = 'Describe where you are (upazila, union, landmark).';
    if (!in_array($f['need'], ['rescue', 'medical', 'food', 'water', 'shelter', 'other'], true)) $errs[] = 'Choose what you need most.';
    if (!throttle('sos', 3, 600)) $errs[] = 'You have sent several requests already. Responders have them. If it is urgent, call 999.';
    if (!$errs) {
        throttle_hit('sos');
        q('INSERT INTO sos_requests (name,phone,district_id,location,people_count,need_type,description) VALUES (?,?,?,?,?,?,?)',
          [$f['name'], $f['phone'], $f['district_id'], $f['location'], $f['people'], $f['need'], $f['desc']]);
        redirect('sos.php?ref=' . db()->lastInsertId());
    }
}
public_head('Emergency SOS'); ?>
<section class="sos-page"><div class="container sos-wrap">
<?php if ($ref): ?>
  <div class="card" style="text-align:center"><div class="sos-big" style="background:var(--green);box-shadow:0 0 0 10px var(--green-l)">✓</div>
    <h1 style="font-size:24px">Your SOS has been received</h1>
    <p style="color:var(--muted);margin:.6rem 0 1rem">Reference <b>#<?= $ref ?></b>. District admins and coordinators can see it now and will call the number you gave. Keep your phone on.</p>
    <p style="margin-bottom:1.2rem"><b>In immediate danger, call 999 or the National EOC on <?= EOC_PHONE ?>.</b></p>
    <a class="btn btn-outline" href="<?= url('') ?>">Back to home</a></div>
<?php else: ?>
  <div class="card">
    <div class="sos-big">SOS</div>
    <h1 style="font-size:24px;text-align:center">Need urgent help?</h1>
    <p style="color:var(--muted);text-align:center;margin:.5rem 0 1.4rem">Fill this in and responders in your district will see it immediately. <b>If life is in danger right now, call 999.</b></p>
    <?php if ($errs): ?><div class="flash error" style="position:static;margin-bottom:1rem" role="alert"><span><?= implode('<br>', array_map('e', $errs)) ?></span></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="two"><div class="field"><label for="name">Your name</label><input type="text" id="name" name="name" required value="<?= old('name') ?>"></div>
      <div class="field"><label for="phone">Mobile number</label><input type="tel" id="phone" name="phone" required placeholder="01XXXXXXXXX" value="<?= old('phone') ?>"></div></div>
      <div class="two"><div class="field"><label for="district_id">District</label><select id="district_id" name="district_id" required><?= district_options((int)($_POST['district_id'] ?? 0)) ?></select></div>
      <div class="field"><label for="people_count">People who need help</label><input type="number" id="people_count" name="people_count" min="1" max="5000" value="<?= e($_POST['people_count'] ?? 1) ?>"></div></div>
      <div class="field"><label for="location">Where are you?</label><input type="text" id="location" name="location" required placeholder="Upazila, union, village or nearby landmark" value="<?= old('location') ?>"></div>
      <div class="field"><label for="need_type">What do you need most?</label><select id="need_type" name="need_type" required><option value="">Select</option><?= opts(['rescue', 'medical', 'food', 'water', 'shelter', 'other'], $_POST['need_type'] ?? null) ?></select></div>
      <div class="field"><label for="description">Details (optional)</label><textarea id="description" name="description" placeholder="Water level, injuries, elderly or children present…"><?= old('description') ?></textarea></div>
      <button class="btn btn-red btn-block" type="submit">Send SOS now</button>
    </form>
  </div>
<?php endif; ?>
</div></section>
<?php public_foot();
