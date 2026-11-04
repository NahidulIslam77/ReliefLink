<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator', 'audit']);
if (is_post()) {
    csrf_check();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['password'] ?? '');
    $full = row('SELECT password_hash FROM users WHERE id=?', [(int)$u['id']]);
    if (!password_verify($cur, $full['password_hash'])) flash('error', 'Your current password is incorrect.');
    elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('error', 'New password needs 8+ characters with letters and numbers.');
    elseif ($new !== ($_POST['password2'] ?? '')) flash('error', 'New passwords do not match.');
    else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]); audit('user.password', 'user', (int)$u['id'], 'Password changed'); flash('success', 'Password updated.'); }
    redirect('dashboard/profile.php');
}
dash_head('My profile', 'profile', ROLES[$u['role']][0]);
?>
<div class="cols c11">
  <section class="panel"><header><h2>Account</h2></header>
    <?php foreach (['Name' => $u['first_name'] . ' ' . $u['last_name'], 'Role' => ROLES[$u['role']][0], 'Email' => $u['email'], 'Phone' => $u['phone'], 'District' => $u['district_name'] ?: 'All districts', 'Organisation' => $u['organization'] ?: '—', 'Member since' => date('j M Y', strtotime($u['created_at']))] as $k => $v): ?>
      <div class="list-row"><span style="color:var(--muted)"><?= e($k) ?></span><b><?= e($v) ?></b></div><?php endforeach; ?></section>
  <section class="panel"><header><h2>Change password</h2></header>
    <form method="post" class="pad"><?= csrf_field() ?>
      <div class="field"><label for="current">Current password</label><input type="password" id="current" name="current" required autocomplete="current-password"></div>
      <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" required autocomplete="new-password"><div class="hint">8+ characters, letters and numbers</div></div>
      <div class="field"><label for="password2">Confirm new password</label><input type="password" id="password2" name="password2" required autocomplete="new-password"></div>
      <button class="btn btn-primary" type="submit">Update password</button></form></section>
</div>
<?php dash_foot();
