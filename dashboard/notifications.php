<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator', 'audit']); $id = (int)$u['id'];
if (isset($_GET['go'])) { $n = row('SELECT * FROM notifications WHERE id=? AND user_id=?', [(int)$_GET['go'], $id]); if ($n) { q('UPDATE notifications SET is_read=1 WHERE id=?', [(int)$n['id']]); redirect('dashboard/' . ($n['link'] ?: 'notifications.php')); } redirect('dashboard/notifications.php'); }
if (is_post()) { csrf_check(); q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$id]); flash('success', 'All notifications marked as read.'); redirect('dashboard/notifications.php'); }
$cats = ['all' => 'All', 'sos' => 'SOS', 'mission' => 'Mission', 'demand' => 'Demand', 'verification' => 'Verification', 'inventory' => 'Inventory', 'disaster' => 'Disaster', 'account' => 'Account', 'system' => 'System'];
$cat = $_GET['cat'] ?? 'all'; if (!isset($cats[$cat])) $cat = 'all';
$list = rows('SELECT * FROM notifications WHERE user_id=?' . ($cat !== 'all' ? ' AND category=?' : '') . ' ORDER BY id DESC LIMIT 80', $cat !== 'all' ? [$id, $cat] : [$id]);
$unread = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$id]);
dash_head('Notifications', 'notifications', $unread ? "$unread unread" : 'You are all caught up');
?>
<div class="page-actions"><?php tabs($cats, $cat, 'cat'); ?><?php if ($unread): ?><form method="post"><?= csrf_field() ?><button class="btn btn-outline btn-sm">Mark all as read</button></form><?php endif; ?></div>
<section class="panel"><?php foreach ($list as $n): ?>
  <div class="nitem <?= $n['is_read'] ? '' : 'unread' ?>"><div style="flex:1"><b><?= e($n['title']) ?></b><?php if ($n['body']): ?><span style="font-size:14px"><?= e($n['body']) ?></span><?php endif; ?><small><?= ago($n['created_at']) ?> · <?= e(ucfirst($n['category'])) ?></small></div>
    <?php if ($n['link']): ?><a class="btn btn-outline btn-xs" href="?go=<?= (int)$n['id'] ?>">Open</a><?php endif; ?></div>
<?php endforeach; if (!$list): ?><div class="empty">No notifications here yet. SOS alerts, assignments and audit decisions show up in this list.</div><?php endif; ?></section>
<?php dash_foot();
