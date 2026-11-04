<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator', 'audit']);
$keys = ['alert_sos', 'alert_mission', 'alert_demand', 'alert_system', 'email', 'sms', 'offline', 'autosync', 'datasaver'];
if (is_post()) {
    csrf_check(); $s = []; foreach ($keys as $k) $s[$k] = isset($_POST[$k]) ? 1 : 0;
    q('UPDATE users SET settings=? WHERE id=?', [json_encode($s), (int)$u['id']]); audit('user.settings', 'user', (int)$u['id'], 'Preferences updated'); flash('success', 'Settings saved.'); redirect('dashboard/settings.php');
}
$s = user_settings($u['settings']);
$tog = fn($k, $t, $d) => '<label class="toggle"><span><b>' . e($t) . '</b><small>' . e($d) . '</small></span><input type="checkbox" name="' . $k . '" ' . ($s[$k] ? 'checked' : '') . '><span class="sw"></span></label>';
dash_head('Settings', 'settings', 'Alerts, offline behaviour and data use');
?>
<form method="post" style="display:grid;gap:1.2rem;max-width:860px"><?= csrf_field() ?>
<section class="panel"><header><h2>Notification preferences</h2></header>
  <?= $tog('alert_sos', 'Emergency SOS alerts', 'Immediate alerts for SOS submissions in your scope') . $tog('alert_mission', 'Mission, verification and stock updates', 'Assignments, dispatches, confirmations, audit decisions and low-stock alerts') . $tog('alert_demand', 'Demand notifications', 'New demands, status changes and matching updates') . $tog('alert_system', 'Disaster, account and system notices', 'New disaster declarations, account changes and maintenance notices') ?></section>
<section class="panel"><header><h2>Delivery channels</h2><span class="sub">In-app alerts are always on</span></header>
  <?= $tog('email', 'Email notifications', 'Receive alerts at ' . $u['email'] . ' when an email gateway is configured') . $tog('sms', 'SMS notifications', 'Critical alerts to ' . $u['phone'] . ' when an SMS gateway is configured') ?></section>
<section class="panel"><header><h2>Offline &amp; data</h2></header>
  <?= $tog('offline', 'Offline mode', 'Save forms on this device when there is no network') . $tog('autosync', 'Auto-sync on connect', 'Send saved records automatically when the connection returns') . $tog('datasaver', 'Data saving mode', 'Hide maps and stop background refresh to save mobile data') ?></section>
<section class="panel"><header><h2>Region</h2></header><div class="kv one"><div><span>Time zone</span><b>Asia/Dhaka (BST, UTC+6)</b></div><div><span>Language</span><b>English</b></div></div></section>
<div><button class="btn btn-primary" type="submit">Save changes</button></div></form>
<?php dash_foot();
