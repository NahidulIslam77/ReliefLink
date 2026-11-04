<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$r = $token ? row('SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>NOW()', [hash('sha256', $token)]) : null;
$err = '';
if ($r && is_post()) {
    csrf_check();
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $err = 'Password needs 8+ characters with letters and numbers.';
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Passwords do not match.';
    else {
        q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $r['user_id']]);
        q('UPDATE password_resets SET used=1 WHERE user_id=?', [$r['user_id']]);
        flash('success', 'Password updated. You can sign in now.');
        redirect('login.php');
    }
}
head('Reset password'); ?>
<div class="center-card"><div class="card">
  <h1 style="font-size:20px;text-align:center;margin-bottom:1rem">Set a new password</h1>
  <?php if (!$r): ?>
    <p class="empty">This reset link is invalid or has expired.</p><p style="text-align:center"><a href="<?= url('forgot-password.php') ?>">Request a new link</a></p>
  <?php else: ?>
    <?php if ($err): ?><div class="flash error" style="position:static;margin-bottom:1rem"><span><?= e($err) ?></span></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
      <div class="field"><label for="password2">Confirm password</label><input type="password" id="password2" name="password2" required autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block" type="submit">Update password</button>
    </form>
  <?php endif; ?>
</div></div>
<script src="<?= url('assets/js/main.js') ?>"></script></body></html>
