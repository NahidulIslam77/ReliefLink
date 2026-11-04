<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'coordinator']);
$role = $u['role']; $admin = $role !== 'coordinator';
[$ds, $dp] = district_scope($u, 's.district_id');

if (is_post()) {
    csrf_check(); $a = post('action');
    if ($a === 'send' && $role === 'coordinator') { [$ok, $msg] = create_sos_alert($u, $_POST); flash($ok ? 'success' : 'error', $msg); redirect('dashboard/sos.php'); }
    $s = row("SELECT s.* FROM sos_requests s WHERE s.id=?$ds", array_merge([(int)post('id')], $dp));
    if (!$s) flash('error', 'SOS request not found in your scope.');
    elseif ($a === 'assign' && $admin) {
        $co = (int)post('coordinator_id') ?: null; $ngo = (int)post('ngo_id') ?: null;
        if (!$co && !$ngo) flash('error', 'Pick a coordinator, an NGO team, or both.');
        elseif ($co && !val("SELECT COUNT(*) FROM users WHERE id=? AND role='coordinator' AND status='active' AND district_id=?", [$co, (int)$s['district_id']])) flash('error', 'Pick an active coordinator from this district.');
        elseif ($ngo && !val("SELECT COUNT(*) FROM users WHERE id=? AND role='ngo' AND status='active'", [$ngo])) flash('error', 'Pick an active NGO team.');
        elseif ($s['status'] === 'resolved') flash('error', 'This request is already resolved.');
        else {
            q("UPDATE sos_requests SET status='assigned', assigned_to=COALESCE(?,assigned_to), assigned_ngo_id=COALESCE(?,assigned_ngo_id) WHERE id=?", [$co, $ngo, (int)$s['id']]);
            audit('sos.assign', 'sos', (int)$s['id'], ucfirst($s['need_type']) . ' · ' . $s['location']);
            $msg = ucfirst($s['need_type']) . ' need at ' . $s['location'] . ' (' . $s['people_count'] . ' people). Call ' . $s['phone'];
            if ($co) notify_user($co, 'SOS assigned to you', $msg, 'sos.php', 'sos'); if ($ngo) notify_user($ngo, 'SOS: your team was asked to respond', $msg, 'tracking.php', 'sos');
            flash('success', 'Responders assigned and notified.');
        }
    } elseif ($a === 'take' && $role === 'coordinator') {
        if ($s['status'] !== 'new') flash('error', 'Someone already picked this request up.');
        else { q("UPDATE sos_requests SET status='assigned', assigned_to=? WHERE id=? AND status='new'", [(int)$u['id'], (int)$s['id']]); audit('sos.take', 'sos', (int)$s['id'], $s['location']); flash('success', 'You are on this one. Call the requester now.'); }
    } elseif ($a === 'resolve') {
        if (!$admin && (int)$s['assigned_to'] !== (int)$u['id'] && (int)$s['reported_by'] !== (int)$u['id']) flash('error', 'Only the assigned coordinator can resolve this request.');
        else { q("UPDATE sos_requests SET status='resolved', resolved_at=NOW() WHERE id=?", [(int)$s['id']]); audit('sos.resolve', 'sos', (int)$s['id'], $s['location']); flash('success', 'Marked as resolved.'); }
    }
    redirect('dashboard/sos.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$st = $_GET['status'] ?? 'open'; $w = $ds; $p = $dp;
if ($st === 'open') $w .= " AND s.status<>'resolved'"; elseif (in_array($st, ['new', 'assigned', 'resolved'], true)) { $w .= ' AND s.status=?'; $p[] = $st; } else $st = 'all';
$list = rows("SELECT s.*, di.name AS district, c.first_name cf, c.last_name cl, n.organization ngo FROM sos_requests s JOIN districts di ON di.id=s.district_id LEFT JOIN users c ON c.id=s.assigned_to LEFT JOIN users n ON n.id=s.assigned_ngo_id WHERE 1=1$w ORDER BY s.status='new' DESC, s.severity='critical' DESC, s.created_at DESC LIMIT 200", $p);
$c = ['new' => 0, 'assigned' => 0, 'resolved' => 0]; foreach (rows("SELECT s.status, COUNT(*) c FROM sos_requests s WHERE 1=1$ds GROUP BY s.status", $dp) as $r) $c[$r['status']] = (int)$r['c'];
$coords = $admin ? rows("SELECT id, first_name, last_name, district_id FROM users WHERE role='coordinator' AND status='active' ORDER BY first_name") : [];
$ngos = $admin ? rows("SELECT id, organization FROM users WHERE role='ngo' AND status='active' ORDER BY organization") : [];
dash_head($role === 'coordinator' ? 'Emergency SOS' : ($role === 'super_admin' ? 'SOS Queue' : 'SOS Monitoring'), 'sos', $role === 'coordinator' ? 'Send an urgent alert, or respond to requests in your district' : 'Emergency requests from citizens and coordinators');
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('red', 'New (unanswered)', $c['new'], 'Need a response now', 'alert', 'r') . kpi('blue', 'Assigned', $c['assigned'], 'Responders on it', 'users', 'b') . kpi('green', 'Resolved', $c['resolved'], 'Closed', 'check', 'g') ?></div>
<?php if ($role === 'coordinator'): $types = ['medical' => ['🏥', 'Medical emergency'], 'rescue' => ['🛟', 'Rescue needed'], 'fire' => ['🔥', 'Fire'], 'flooding' => ['🌊', 'Sudden flooding'], 'food' => ['🍚', 'Severe food shortage'], 'other' => ['⚠️', 'Other emergency']]; ?>
<div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00" data-offline-banner hidden><span><b>You are offline</b><span class="sub">Your SOS alert will be saved and sent the moment you reconnect. If life is in danger, call 999.</span></span></div>
<section class="panel sos-form-card"><div class="pad"><div class="sos-banner"><div class="sos-big">SOS</div><b>Emergency alert</b><div style="font-size:13.5px;margin-top:.3rem">Your District Admin and nearby NGO teams are notified immediately. If life is in danger, also call 999.</div></div></div>
<form method="post" class="pad" style="padding-top:0" data-offline-type="sos" data-offline-label="SOS alert"><?= csrf_field() ?><input type="hidden" name="action" value="send"><input type="hidden" name="client_uid" value="">
  <div class="field"><label>Emergency type *</label><div class="cat-grid"><?php foreach ($types as $k => [$em, $l]): ?><div class="cat red"><input type="radio" name="need_type" id="t_<?= $k ?>" value="<?= $k ?>" required><label for="t_<?= $k ?>"><span class="e"><?= $em ?></span><span><b><?= $l ?></b></span></label></div><?php endforeach; ?></div></div>
  <div class="field"><label for="description">Description *</label><textarea id="description" name="description" maxlength="1000" required placeholder="Describe the emergency clearly: how many people, what is needed, any dangers…"></textarea></div>
  <div class="three"><div class="field"><label for="people_count">People affected</label><input type="number" id="people_count" name="people_count" min="1" max="5000" value="1"></div><div class="field"><label for="severity">Severity</label><select id="severity" name="severity"><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option></select></div><div class="field"><label for="phone">Your contact number</label><input type="tel" id="phone" name="phone" value="<?= e($u['phone']) ?>"></div></div>
  <div class="field"><label>GPS location</label><div class="geo-row"><input type="text" id="gps_lat" name="gps_lat" placeholder="Latitude"><input type="text" id="gps_lng" name="gps_lng" placeholder="Longitude"><button type="button" class="btn btn-outline" data-geo="gps_lat,gps_lng,geo_state"><?= icon('pin') ?>Detect</button></div><div class="geo-state" id="geo_state"></div></div>
  <button class="btn btn-red btn-block" type="submit">Send SOS alert</button></form></section>
<?php endif;
tabs(['open' => 'Open', 'new' => 'New', 'assigned' => 'Assigned', 'resolved' => 'Resolved', 'all' => 'All'], $st); ?>
<section class="panel"><table class="tbl"><thead><tr><th>Request</th><th>Contact</th><th>Where</th><th class="num">People</th><th>Details</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
<?php foreach ($list as $s): ?>
  <tr><td><b>#<?= (int)$s['id'] ?> · <?= e(SOS_TYPES[$s['need_type']] ?? ucfirst($s['need_type'])) ?></b><br><?= badge($s['severity']) ?> <span class="chip"><?= $s['source'] === 'coordinator' ? 'Coordinator' : 'Public' ?></span><small><?= ago($s['created_at']) ?></small></td>
    <td><?= e($s['name']) ?><small><a href="tel:<?= e($s['phone']) ?>"><?= e($s['phone']) ?></a></small></td><td><?= e($s['district']) ?><small><?= e($s['location']) ?></small></td><td class="num"><?= (int)$s['people_count'] ?></td>
    <td style="max-width:240px"><small style="color:var(--ink)"><?= e($s['description'] ?: '—') ?></small></td>
    <td><?= badge($s['status']) ?><?php if ($s['cf']): ?><small>📞 <?= e($s['cf'] . ' ' . $s['cl']) ?></small><?php endif; ?><?php if ($s['ngo']): ?><small>🚚 <?= e($s['ngo']) ?></small><?php endif; ?></td>
    <td><div class="actions" style="flex-wrap:wrap"><?php if ($s['status'] !== 'resolved'): ?>
      <?php if ($admin): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <select name="coordinator_id" aria-label="Coordinator" style="max-width:120px"><option value="">Coordinator…</option><?php foreach ($coords as $co) if ((int)$co['district_id'] === (int)$s['district_id']): ?><option value="<?= (int)$co['id'] ?>" <?= (int)$s['assigned_to'] === (int)$co['id'] ? 'selected' : '' ?>><?= e($co['first_name'] . ' ' . $co['last_name']) ?></option><?php endif; ?></select>
        <select name="ngo_id" aria-label="NGO team" style="max-width:120px"><option value="">NGO team…</option><?php foreach ($ngos as $n): ?><option value="<?= (int)$n['id'] ?>" <?= (int)$s['assigned_ngo_id'] === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['organization']) ?></option><?php endforeach; ?></select><button class="btn btn-red btn-xs">Assign</button></form><?php endif; ?>
      <?php if ($role === 'coordinator' && $s['status'] === 'new'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="take"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-red btn-xs">Take this request</button></form><?php endif; ?>
      <?php if ($admin || (int)$s['assigned_to'] === (int)$u['id'] || (int)$s['reported_by'] === (int)$u['id']): ?><form method="post" data-confirm="Mark this SOS as resolved?"><?= csrf_field() ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-green btn-xs">Resolve</button></form><?php endif; ?>
    <?php else: ?><small>Closed <?= ago($s['resolved_at']) ?></small><?php endif; ?></div></td></tr>
<?php endforeach; if (!$list) echo empty_row(7, 'No SOS requests in this view.'); ?></tbody></table></section>
<?php dash_foot();
