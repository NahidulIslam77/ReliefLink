<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['ngo']); $id = (int)$u['id'];
$TYPES = ['Truck', 'Pickup', 'Motor boat', 'Speed boat', 'Van', 'Ambulance', 'Other'];
if (is_post()) {
    csrf_check(); $a = post('action');
    if ($a === 'add') {
        $plate = strtoupper(post('plate'));
        if ($plate === '' || mb_strlen($plate) > 30) flash('error', 'Enter the plate or registration number.');
        elseif (!in_array(post('type'), $TYPES, true)) flash('error', 'Choose a vehicle type.');
        elseif (val('SELECT COUNT(*) FROM vehicles WHERE ngo_id=? AND plate=?', [$id, $plate])) flash('error', 'You already registered that vehicle.');
        else { q('INSERT INTO vehicles (ngo_id,plate,type,capacity) VALUES (?,?,?,?)', [$id, $plate, post('type'), mb_substr(post('capacity'), 0, 40)]); audit('vehicle.add', 'vehicle', (int)db()->lastInsertId(), $plate); flash('success', 'Vehicle added.'); }
    } else {
        $v = row('SELECT * FROM vehicles WHERE id=? AND ngo_id=?', [(int)post('id'), $id]);
        if (!$v) flash('error', 'Vehicle not found.');
        elseif ($v['status'] === 'on_mission') flash('error', 'This vehicle is on a mission. Finish the delivery first.');
        elseif ($a === 'toggle') { q('UPDATE vehicles SET status=? WHERE id=?', [$v['status'] === 'maintenance' ? 'available' : 'maintenance', (int)$v['id']]); flash('success', 'Vehicle status updated.'); }
        elseif ($a === 'delete') { if (val("SELECT COUNT(*) FROM relief_missions WHERE vehicle_id=? AND status IN ('accepted','in_transit','arrived')", [(int)$v['id']])) flash('error', 'This vehicle is reserved for an active mission.'); else { q('UPDATE relief_missions SET vehicle_id=NULL WHERE vehicle_id=?', [(int)$v['id']]); q('DELETE FROM vehicles WHERE id=?', [(int)$v['id']]); audit('vehicle.remove', 'vehicle', (int)$v['id'], $v['plate']); flash('success', 'Vehicle removed.'); } }
    }
    redirect('dashboard/vehicles.php');
}
$list = rows("SELECT v.*, (SELECT m.code FROM relief_missions m WHERE m.vehicle_id=v.id AND m.status IN ('accepted','in_transit','arrived') LIMIT 1) mcode FROM vehicles v WHERE v.ngo_id=? ORDER BY v.status, v.plate", [$id]);
dash_head('Vehicle Management', 'vehicles', $u['organization']);
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('', 'Vehicles', count($list), 'Registered', 'truck', 'b') . kpi('green', 'Available', count(array_filter($list, fn($v) => $v['status'] === 'available')), 'Ready to dispatch', 'check', 'g') . kpi('amber', 'In maintenance', count(array_filter($list, fn($v) => $v['status'] === 'maintenance')), 'Not usable', 'alert', 'a') ?></div>
<details class="panel"><summary>Add a vehicle</summary><form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="three"><div class="field"><label for="plate">Plate / registration</label><input type="text" id="plate" name="plate" required maxlength="30" placeholder="DHA-X-5521"></div><div class="field"><label for="type">Type</label><select id="type" name="type"><?php foreach ($TYPES as $t) echo '<option>' . e($t) . '</option>'; ?></select></div><div class="field"><label for="capacity">Capacity</label><input type="text" id="capacity" name="capacity" maxlength="40" placeholder="e.g. 5 ton"></div></div><button class="btn btn-primary">Add vehicle</button></form></details>
<section class="panel"><table class="tbl"><thead><tr><th>Vehicle</th><th>Type</th><th>Capacity</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
<?php foreach ($list as $v): ?><tr><td><b><?= e($v['plate']) ?></b></td><td><?= e($v['type']) ?></td><td><?= e($v['capacity'] ?: '—') ?></td><td><?= $v['status'] === 'on_mission' ? '<span class="badge b-blue">On mission</span><small>' . e($v['mcode']) . '</small>' : ($v['status'] === 'available' ? '<span class="badge b-green">Available</span>' : '<span class="badge b-amber">Maintenance</span>') ?></td>
  <td><div class="actions"><?php if ($v['status'] !== 'on_mission'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-outline btn-xs" name="action" value="toggle"><?= $v['status'] === 'maintenance' ? 'Mark available' : 'Send to maintenance' ?></button><button class="btn btn-outline btn-xs" name="action" value="delete" style="color:var(--red-d)" onclick="return confirm('Remove this vehicle?')">Remove</button></form><?php endif; ?></div></td></tr>
<?php endforeach; if (!$list) echo empty_row(5, 'Add the vehicles your team can dispatch.'); ?></tbody></table></section>
<?php dash_foot();
