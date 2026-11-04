<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
if (user()) redirect('dashboard/');

$errs = [];
if (is_post()) {
    csrf_check();
    $f = ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'email' => strtolower(post('email')), 'phone' => preg_replace('/[\s-]/', '', post('phone')),
          'role' => post('role'), 'district_id' => (int)post('district_id'), 'organization' => post('organization')];
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if ($f['first_name'] === '' || $f['last_name'] === '') $errs[] = 'Enter your first and last name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email address.';
    if (!preg_match('/^(?:\+?88)?(01[3-9]\d{8})$/', $f['phone'], $m)) $errs[] = 'Enter a valid Bangladesh mobile number (01XXXXXXXXX).'; else $f['phone'] = $m[1];
    if (!in_array($f['role'], SIGNUP_ROLES, true)) $errs[] = 'Choose a role.';
    if (in_array($f['role'], ['district_admin', 'coordinator'], true) && !$f['district_id']) $errs[] = 'Select your district.';
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errs[] = 'Password needs 8+ characters with letters and numbers.';
    if ($pw !== $pw2) $errs[] = 'Passwords do not match.';
    if (!isset($_POST['terms'])) $errs[] = 'Please accept the terms to continue.';
    if (!$errs && val('SELECT COUNT(*) FROM users WHERE email=? OR phone=?', [$f['email'], $f['phone']])) $errs[] = 'An account with this email or phone already exists.';
    if (!$errs) {
        q('INSERT INTO users (first_name,last_name,email,phone,password_hash,role,district_id,organization,status) VALUES (?,?,?,?,?,?,?,?,"pending")',
          [$f['first_name'], $f['last_name'], $f['email'], $f['phone'], password_hash($pw, PASSWORD_DEFAULT), $f['role'], $f['district_id'] ?: null, $f['organization'] ?: null]);
        flash('success', 'Account created. The National Admin will verify and activate it, then you can sign in.');
        redirect('login.php');
    }
}
head('Create account', 'auth-page'); ?>
<div class="auth">
  <?php auth_side("Join Bangladesh's Relief Network", 'Register your organisation or team to coordinate relief with government and verified partners. New accounts are reviewed before activation.', true); ?>
  <div class="auth-main"><div class="auth-form" style="width:min(520px,100%)">
    <h1>Create your account</h1><p class="sub">Register as a responder or official. Citizens in need can use <a href="<?= url('sos.php') ?>">Emergency SOS</a> without an account.</p>
    <?php if ($errs): ?><div class="flash error" style="position:static;margin-bottom:1rem" role="alert"><span><?= implode('<br>', array_map('e', $errs)) ?></span></div><?php endif; ?>
    <form method="post" novalidate><?= csrf_field() ?>
      <div class="two"><div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" required value="<?= old('first_name') ?>"></div>
      <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" required value="<?= old('last_name') ?>"></div></div>
      <div class="two"><div class="field"><label for="email">Email</label><input type="email" id="email" name="email" required value="<?= old('email') ?>"></div>
      <div class="field"><label for="phone">Mobile number</label><input type="tel" id="phone" name="phone" required placeholder="01XXXXXXXXX" value="<?= old('phone') ?>"></div></div>
      <div class="two"><div class="field"><label for="role">I am a</label><select id="role" name="role" required><option value="">Select role</option><?php foreach (SIGNUP_ROLES as $r): ?><option value="<?= $r ?>" <?= ($_POST['role'] ?? '') === $r ? 'selected' : '' ?>><?= e(ROLES[$r][0]) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="district_id">District</label><select id="district_id" name="district_id"><?= district_options((int)($_POST['district_id'] ?? 0)) ?></select></div></div>
      <div class="field"><label for="organization">Organisation / office</label><input type="text" id="organization" name="organization" placeholder="e.g. Bangladesh Red Crescent" value="<?= old('organization') ?>"></div>
      <div class="two"><div class="field"><label for="password">Password</label><div class="pw"><input type="password" id="password" name="password" required autocomplete="new-password"><button type="button" aria-label="Show password">👁</button></div><div class="hint">8+ characters, letters and numbers</div></div>
      <div class="field"><label for="password2">Confirm password</label><input type="password" id="password2" name="password2" required autocomplete="new-password"></div></div>
      <label class="check" style="margin-bottom:1.1rem"><input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?>> I agree to the terms and confirm my details are accurate.</label>
      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p style="text-align:center;margin-top:1.1rem;font-size:14px;color:var(--muted)">Already registered? <a href="<?= url('login.php') ?>">Sign in</a></p>
  </div></div>
</div>
<script src="<?= url('assets/js/main.js') ?>"></script></body></html>
