<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'audit']);
$f = trim((string)($_GET['action'] ?? '')); $w = ''; $p = [];
$groups = array_column(rows("SELECT DISTINCT SUBSTRING_INDEX(action,'.',1) g FROM audit_logs ORDER BY g"), 'g');
if (in_array($f, $groups, true)) { $w = ' WHERE a.action LIKE ?'; $p[] = $f . '.%'; } else $f = '';
$list = rows("SELECT a.*, u.first_name, u.last_name, u.role FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id$w ORDER BY a.id DESC LIMIT 300", $p);
dash_head('Audit log', 'audit', 'Every sensitive action with who did it and when');
?>
<form class="toolbar" method="get"><label for="action" style="font-weight:600;font-size:13.5px">Show</label>
  <select id="action" name="action" onchange="this.form.submit()"><option value="">All activity</option><?php foreach ($groups as $g): ?><option value="<?= e($g) ?>" <?= $f === $g ? 'selected' : '' ?>><?= e(ucfirst($g)) ?> events</option><?php endforeach; ?></select><noscript><button class="btn btn-outline btn-sm">Filter</button></noscript>
  <span style="color:var(--muted);font-size:13px">Latest <?= count($list) ?> entries</span></form>
<section class="panel"><table class="tbl"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach ($list as $a): ?>
  <tr><td style="white-space:nowrap"><?= e(date('j M Y, H:i', strtotime($a['created_at']))) ?></td>
    <td><?= $a['first_name'] ? e($a['first_name'] . ' ' . $a['last_name']) . '<small>' . e(ROLES[$a['role']][0]) . '</small>' : '<small>System / deleted user</small>' ?></td>
    <td><code><?= e($a['action']) ?></code></td><td><?= $a['entity'] ? e($a['entity']) . ' #' . (int)$a['entity_id'] : '—' ?></td><td><?= e($a['detail']) ?></td><td><small><?= e($a['ip']) ?></small></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="empty">No audit entries yet.</td></tr><?php endif; ?>
</tbody></table></section>
<?php dash_foot();
