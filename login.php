<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
if (user()) redirect('dashboard/');

$role = $_POST['role'] ?? 'district_admin';
$err = '';
if (is_post()) {
    csrf_check();
    $id = post('identifier'); $pw = (string)($_POST['password'] ?? '');
    if (!isset(ROLES[$role])) $role = 'district_admin';
    if (!throttle('login')) {
        $err = 'Too many attempts. Please wait 5 minutes and try again.';
    } else {
        $u = row('SELECT * FROM users WHERE email=? OR phone=?', [$id, preg_replace('/^\+?88/', '', $id)]);
        if (!$u || !password_verify($pw, $u['password_hash'])) {
            throttle_hit('login'); $err = 'Incorrect email/phone or password.';
        } elseif ($u['role'] !== $role) {
            $err = 'This account is registered as ' . ROLES[$u['role']][0] . '. Select that role to continue.';
        } elseif ($u['status'] === 'pending') {
            $err = 'Your account is waiting for approval by the National Admin.';
        } elseif ($u['status'] === 'suspended') {
            $err = 'This account is suspended. Contact the National EOC.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            q('UPDATE users SET last_login=NOW() WHERE id=?', [$u['id']]);
            q('INSERT INTO audit_logs (user_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)', [$u['id'], 'auth.login', 'user', $u['id'], ROLES[$u['role']][0], $_SERVER['REMOTE_ADDR'] ?? '']);
            if (isset($_POST['remember'])) setcookie(session_name(), session_id(), ['expires' => time() + 2592000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            flash('success', 'Welcome back, ' . $u['first_name'] . '.');
            redirect('dashboard/');
        }
    }
}
head('Sign in', 'auth-page'); ?>
<div class="auth">
  <?php auth_side("Bangladesh's National<br>Disaster Coordination Platform", 'Secure, role-based access for government officials, NGO field teams, spot coordinators and auditors across all 64 districts.'); ?>
  <div class="auth-main"><div class="auth-form">
    <h1>Welcome back</h1><p class="sub">Sign in to your ReliefLink account</p>
    <?php if ($err): ?><div class="flash error" style="position:static;margin-bottom:1rem" role="alert"><span><?= e($err) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label id="rl">Sign in as</label></div>
      <div class="roles" role="radiogroup" aria-labelledby="rl" style="margin-top:-.6rem">
        <?php foreach (ROLES as $k => [$label, $desc, $ic]): ?>
          <div class="role"><input type="radio" name="role" id="r_<?= $k ?>" value="<?= $k ?>" data-label="<?= e($label) ?>" <?= $role === $k ? 'checked' : '' ?>>
            <label for="r_<?= $k ?>"><span><?= $ic ?></span><span><b><?= e($label) ?></b><small><?= e($desc) ?></small></span></label></div>
        <?php endforeach; ?>
      </div>
      <div class="field"><label for="identifier">Email address or phone</label><input type="text" id="identifier" name="identifier" required autocomplete="username" placeholder="email@organization.gov.bd or 01XXXXXXXXX" value="<?= old('identifier') ?>"></div>
      <div class="field"><label for="password">Password</label><div class="pw"><input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" aria-label="Show password">👁</button></div></div>
      <div class="row-between"><label class="check"><input type="checkbox" name="remember"> Remember me</label><a href="<?= url('forgot-password.php') ?>">Forgot password?</a></div>
      <button class="btn btn-primary btn-block" id="loginBtn" type="submit">Sign in</button>
    </form>
    <p style="text-align:center;margin-top:1.1rem;font-size:14px;color:var(--muted)">Don't have an account? <a href="<?= url('signup.php') ?>">Create account</a></p>
    <div class="warn"><b>⚠ Authorized personnel only</b>Access is restricted to verified government officials, registered NGOs and approved volunteers. Unauthorized access attempts are logged.</div>
    <div class="demo"><b>Demo logins</b> (password <code>Relief@123</code>): <code>admin@relieflink.gov.bd</code> · <code>district@relieflink.gov.bd</code> · <code>ngo@relieflink.org</code> · <code>spot@relieflink.org</code> · <code>audit@relieflink.gov.bd</code>. Remove from this file before going live.</div>
  </div></div>
</div>
<script src="<?= url('assets/js/main.js') ?>"></script></body></html>
