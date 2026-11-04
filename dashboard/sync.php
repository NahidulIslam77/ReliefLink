<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['coordinator']);
dash_head('Offline & Sync', 'sync', 'Records saved on this device while you had no signal');
?>
<div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00"><span><b>How offline mode works</b><span class="sub">When you have no connection, demands, SOS alerts and delivery confirmations are saved on this device and sent automatically once you are back online. Photo evidence always needs a connection.</span></span><button class="btn btn-primary btn-sm" type="button" data-sync-now>Sync now</button></div>
<div class="cols c11"><section class="panel"><header><h2>Connection status</h2></header>
  <div class="conn"><span>Internet connection</span><span data-conn="net">…</span></div><div class="conn"><span>ReliefLink server</span><span data-conn="server">…</span></div><div class="conn"><span>GPS / location access</span><span data-conn="gps">…</span></div><div class="conn"><span>Offline storage</span><span data-conn="store">…</span></div>
  <?php $st = user_settings($u['settings']); ?><div class="conn"><span>Offline mode / auto-sync</span><span><?= $st['offline'] ? 'On' : 'Off' ?> / <?= $st['autosync'] ? 'On' : 'Off' ?> <a href="<?= url('dashboard/settings.php') ?>" style="font-size:13px">change</a></span></div></section>
  <section class="panel"><header><h2>Offline queue</h2></header><div data-queue-list></div></section></div>
<section class="panel"><header><h2>Sync history</h2><button class="btn btn-outline btn-xs" type="button" data-clear-log>Clear history</button></header><div data-sync-log></div></section>
<?php dash_foot();
