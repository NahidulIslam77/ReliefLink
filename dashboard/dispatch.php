<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['ngo']); $id = (int)$u['id'];

if (is_post()) {
    csrf_check();
    $m = row("SELECT m.*, d.district_id FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE m.id=? AND m.ngo_id=? AND m.status='accepted'", [(int)post('mission_id'), $id]);
    $veh = row('SELECT * FROM vehicles WHERE id=? AND ngo_id=?', [(int)post('vehicle_id'), $id]); $drv = row('SELECT * FROM team_members WHERE id=? AND ngo_id=? AND is_driver=1 AND status="active"', [(int)post('driver_id'), $id]);
    $qty = (int)post('cargo_qty'); $dep = post('planned_departure') ? strtotime(post('planned_departure')) : null; $eta = post('eta') ? strtotime(post('eta')) : null;
    $members = array_values(array_filter(array_map('intval', (array)($_POST['members'] ?? []))));
    $okMembers = $members ? (int)val('SELECT COUNT(*) FROM team_members WHERE ngo_id=? AND status="active" AND id IN (' . implode(',', array_fill(0, count($members), '?')) . ')', array_merge([$id], $members)) === count($members) : true;
    if (!$m) flash('error', 'Choose one of your accepted missions.');
    elseif (!$veh || ($veh['status'] === 'maintenance') || ($veh['status'] === 'on_mission' && (int)$m['vehicle_id'] !== (int)$veh['id'])) flash('error', 'Choose a vehicle that is available.');
    elseif (!$drv) flash('error', 'Choose an active driver.');
    elseif ($qty < 1 || $qty > (int)$m['target_qty']) flash('error', 'Cargo quantity must be between 1 and ' . number_format($m['target_qty']) . '.');
    elseif ($dep && $eta && $eta < $dep) flash('error', 'The estimated arrival must be after the planned departure.');
    elseif (!$okMembers) flash('error', 'Pick team members from your active team.');
    else {
        $cargo = post('cargo') ?: number_format($qty) . ' ' . $m['unit'];
        q("UPDATE relief_missions SET status='in_transit', vehicle_id=?, driver_id=?, cargo=?, dispatched_qty=?, planned_departure=?, eta=?, dispatched_at=NOW(), progress=5, confirmation_code=?, notes=? WHERE id=?",
          [(int)$veh['id'], (int)$drv['id'], mb_substr($cargo, 0, 200), $qty, $dep ? date('Y-m-d H:i:s', $dep) : null, $eta ? date('Y-m-d H:i:s', $eta) : null, confirm_code(), mb_substr(post('notes'), 0, 300) ?: null, (int)$m['id']]);
        q('DELETE FROM mission_members WHERE mission_id=?', [(int)$m['id']]); foreach ($members as $mm) q('INSERT INTO mission_members (mission_id,member_id) VALUES (?,?)', [(int)$m['id'], $mm]);
        q("UPDATE vehicles SET status='on_mission' WHERE id=?", [(int)$veh['id']]);
        if ($m['demand_id']) q("UPDATE demands SET status='dispatched' WHERE id=?", [(int)$m['demand_id']]);
        mission_event((int)$m['id'], 'dispatched', 'Dispatched with ' . $veh['plate'] . ', driver ' . $drv['name']);
        audit('mission.dispatch', 'mission', (int)$m['id'], $m['title'] . ' · ' . $veh['plate']);
        $msg = $m['title'] . ' is on the way' . ($eta ? ', ETA ' . date('H:i', $eta) : '') . '.';
        if ($m['coordinator_id']) notify_user((int)$m['coordinator_id'], 'Relief dispatched to your zone', $msg . ' Your confirmation code is on the Delivery Confirmation page.', 'confirm.php', 'mission');
        notify_where("role='district_admin' AND district_id=?", [(int)$m['district_id']], 'Mission dispatched', $msg, 'tracking.php?id=' . (int)$m['id'], 'mission');
        flash('success', 'Team dispatched. Track progress under Live Tracking.');
        redirect('dashboard/tracking.php?id=' . (int)$m['id']);
    }
    redirect('dashboard/dispatch.php?mission=' . (int)post('mission_id'));
}

$ready = rows("SELECT m.*, z.name zone FROM relief_missions m LEFT JOIN zones z ON z.id=m.zone_id WHERE m.ngo_id=? AND m.status='accepted' ORDER BY FIELD(m.priority,'critical','high','medium','low'), m.created_at", [$id]);
$sel = (int)($_GET['mission'] ?? ($ready[0]['id'] ?? 0));
$cur = null; foreach ($ready as $r) if ((int)$r['id'] === $sel) $cur = $r;
$veh = rows("SELECT * FROM vehicles WHERE ngo_id=? AND status<>'maintenance' ORDER BY status='available' DESC, plate", [$id]);
$drv = rows("SELECT * FROM team_members WHERE ngo_id=? AND is_driver=1 AND status='active' ORDER BY name", [$id]);
$mem = rows("SELECT * FROM team_members WHERE ngo_id=? AND status='active' ORDER BY name", [$id]);
$moving = rows("SELECT m.*, z.name zone, v.plate FROM relief_missions m LEFT JOIN zones z ON z.id=m.zone_id LEFT JOIN vehicles v ON v.id=m.vehicle_id WHERE m.ngo_id=? AND m.status IN ('in_transit','arrived') ORDER BY m.dispatched_at DESC", [$id]);
dash_head('Dispatch Relief', 'dispatch', 'Send an accepted mission on the road');
?>
<?php if (!$ready): ?><div class="alert blue"><span><b>No accepted missions are waiting to be dispatched.</b><span class="sub">Accept a demand first, then come back here to send your team.</span></span><a class="btn btn-outline btn-sm" href="<?= url('dashboard/available.php') ?>">Find demands</a></div><?php else: ?>
<section class="panel"><header><h2>Dispatch Relief: New Mission</h2></header>
<form method="post" class="pad"><?= csrf_field() ?>
  <div class="two"><div class="field"><label for="mission_id">Mission / demand</label><select id="mission_id" name="mission_id" onchange="location='?mission='+this.value"><?php foreach ($ready as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $cur && (int)$cur['id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['code'] . ' · ' . $r['title']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="vehicle_id">Vehicle</label><select id="vehicle_id" name="vehicle_id" required><option value="">Select vehicle</option><?php foreach ($veh as $x): $ok = $x['status'] === 'available' || ($cur && (int)$cur['vehicle_id'] === (int)$x['id']); ?><option value="<?= (int)$x['id'] ?>" <?= $cur && (int)$cur['vehicle_id'] === (int)$x['id'] ? 'selected' : '' ?> <?= $ok ? '' : 'disabled' ?>><?= e($x['plate'] . ' · ' . $x['type'] . ' (' . $x['capacity'] . ')' . ($ok ? '' : ' · on a mission')) ?></option><?php endforeach; ?></select></div></div>
  <div class="two"><div class="field"><label for="driver_id">Driver</label><select id="driver_id" name="driver_id" required><option value="">Select driver</option><?php foreach ($drv as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $cur && (int)$cur['driver_id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="cargo_qty">Cargo quantity (<?= e($cur['unit'] ?? 'units') ?>)</label><input type="number" id="cargo_qty" name="cargo_qty" min="1" required value="<?= (int)($cur['target_qty'] ?? 1) ?>"></div></div>
  <div class="field"><label for="cargo">Cargo manifest</label><input type="text" id="cargo" name="cargo" maxlength="200" placeholder="e.g. 400 food packages, 200 water cans"></div>
  <div class="two"><div class="field"><label for="planned_departure">Planned departure</label><input type="datetime-local" id="planned_departure" name="planned_departure" value="<?= date('Y-m-d\TH:i') ?>"></div><div class="field"><label for="eta">Estimated arrival</label><input type="datetime-local" id="eta" name="eta" value="<?= date('Y-m-d\TH:i', time() + 3 * 3600) ?>"></div></div>
  <div class="field"><label>Team members</label><div class="three"><?php foreach ($mem as $x): ?><label class="check"><input type="checkbox" name="members[]" value="<?= (int)$x['id'] ?>" <?= $cur && val('SELECT COUNT(*) FROM mission_members WHERE mission_id=? AND member_id=?', [(int)$cur['id'], (int)$x['id']]) ? 'checked' : '' ?>> <span><b style="font-size:14px"><?= e($x['name']) ?></b><br><small style="color:var(--muted)"><?= e($x['title']) ?></small></span></label><?php endforeach; if (!$mem) echo '<small>Add team members under Team Management.</small>'; ?></div></div>
  <div class="field"><label for="notes">Dispatch notes</label><textarea id="notes" name="notes" maxlength="300" placeholder="Route instructions, special handling notes…"><?= e($cur['notes'] ?? '') ?></textarea></div>
  <button class="btn btn-primary" type="submit">Dispatch team</button></form></section><?php endif; ?>
<?php if ($moving): ?><section class="panel"><header><h2>On the road</h2></header><?php foreach ($moving as $m): ?><div class="list-row"><span><code><?= e($m['code']) ?></code> <b><?= e($m['title']) ?></b><small><?= e($m['plate']) ?> · <?= e($m['zone']) ?> · dispatched <?= ago($m['dispatched_at']) ?></small></span><span style="display:flex;gap:.6rem;align-items:center"><?= badge($m['status']) ?><a class="btn btn-outline btn-xs" href="<?= url('dashboard/tracking.php?id=' . (int)$m['id']) ?>">Track</a></span></div><?php endforeach; ?></section><?php endif; ?>
<?php dash_foot();
