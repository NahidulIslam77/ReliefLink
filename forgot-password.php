<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$link = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    if (throttle('forgot', 5, 900)) {
        throttle_hit('forgot');
        $u = row('SELECT id,email FROM users WHERE email=? AND status<>"suspended"', [$email]);
        if ($u) {
            $token = bin2hex(random_bytes(24));
            q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))', [$u['id'], hash('sha256', $token)]);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('reset-password.php?t=' . $token);
            if (!DEV_SHOW_RESET_LINK) { @mail($u['email'], APP_NAME . ' password reset', "Reset your password (valid for 1 hour):\n$link"); $link = ''; }
        }
        flash('info', 'If that email is registered, a reset link has been sent.');
    } else flash('error', 'Too many requests. Try again later.');
}
head('Forgot password'); ?>
<div class="center-card"><div class="card">
  <div style="text-align:center;margin-bottom:1rem"><span style="display:inline-block;background:var(--navy);padding:.5rem .8rem;border-radius:10px"><?= logo() ?></span></div>
  <h1 style="font-size:20px;text-align:center">Forgot your password?</h1>
  <p style="color:var(--muted);text-align:center;margin:.4rem 0 1.3rem;font-size:14px">Enter your email and we'll send a reset link.</p>
  <?php if ($link): ?><div class="warn" style="margin:0 0 1rem;word-break:break-all"><b>Development mode</b>No mail server is configured, so use this link: <a href="<?= e($link) ?>"><?= e($link) ?></a></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" required></div>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:14px"><a href="<?= url('login.php') ?>">← Back to sign in</a></p>
</div></div>
<script src="<?= url('assets/js/main.js') ?>"></script></body></html>
