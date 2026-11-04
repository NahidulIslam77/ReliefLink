<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['coordinator']);
if (is_post()) {
    csrf_check();
    [$ok, $msg] = create_demand($u, $_POST);
    flash($ok ? 'success' : 'error', $msg);
    if ($ok) redirect('dashboard/demands.php');
}
$zones = rows('SELECT id, name FROM zones WHERE district_id=? AND status<>"resolved" ORDER BY name', [(int)$u['district_id']]);
$cats = ['food' => ['🍚', 'Food', 'Dry food, rice, dal'], 'water' => ['💧', 'Water', 'Drinking water, jerry cans'], 'medicine' => ['💊', 'Medicine', 'ORS, medicine kits'], 'shelter' => ['⛺', 'Shelter', 'Tarpaulins, tents'], 'hygiene' => ['🧼', 'Hygiene', 'Soap, sanitary kits'], 'clothing' => ['🧥', 'Clothing', 'Clothes, blankets'], 'rescue' => ['🚤', 'Rescue', 'Boats, emergency rescue'], 'other' => ['📦', 'Other', 'Fuel, tools, other']];
$sel = $_POST['item_type'] ?? 'food';
dash_head('Submit Demand', 'demand-new', 'Report what your zone needs');
?>
<div class="alert" style="background:var(--amber-l);border-color:#F3D88A;color:#6F4F00" data-offline-banner hidden><span><b>You are offline</b><span class="sub">Your demand will be saved on this device and sent automatically when you reconnect.</span></span></div>
<section class="panel"><header><h2>Submit Relief Demand</h2></header>
<form method="post" class="pad" data-offline-type="demand" data-offline-label="Demand submission"><?= csrf_field() ?><input type="hidden" name="client_uid" value="">
  <div class="field"><label>Relief category *</label><div class="cat-grid" style="grid-template-columns:repeat(4,1fr)"><?php foreach ($cats as $k => [$em, $l, $d]): ?><div class="cat"><input type="radio" name="item_type" id="c_<?= $k ?>" value="<?= $k ?>" data-unit-for="<?= e(DEMAND_UNITS[$k]) ?>" <?= $sel === $k ? 'checked' : '' ?>><label for="c_<?= $k ?>"><span class="e"><?= $em ?></span><span><b><?= $l ?></b><small><?= $d ?></small></span></label></div><?php endforeach; ?></div></div>
  <div class="three"><div class="field"><label for="quantity">Quantity needed *</label><input type="number" id="quantity" name="quantity" min="1" required value="<?= old('quantity') ?>" placeholder="e.g. 200"></div>
    <div class="field"><label for="unit">Unit</label><input type="text" id="unit" name="unit" maxlength="30" value="<?= e($_POST['unit'] ?? DEMAND_UNITS[$sel] ?? 'packages') ?>"></div>
    <div class="field"><label for="households">Households affected *</label><input type="number" id="households" name="households" min="1" required value="<?= old('households') ?>" placeholder="e.g. 200"></div></div>
  <div class="three"><div class="field"><label for="priority">Urgency *</label><select id="priority" name="priority"><?= opts(PRIORITIES, $_POST['priority'] ?? 'high') ?></select></div>
    <div class="field"><label for="access_mode">Access mode</label><select id="access_mode" name="access_mode"><?php foreach (ACCESS_MODES as $a): ?><option <?= ($_POST['access_mode'] ?? 'Road') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="zone_id">Zone</label><?php if ($u['zone_id']): ?><input type="hidden" name="zone_id" value="<?= (int)$u['zone_id'] ?>"><input type="text" value="<?= e(val('SELECT name FROM zones WHERE id=?', [(int)$u['zone_id']])) ?>" disabled><?php else: ?><select id="zone_id" name="zone_id" required><option value="">Select zone</option><?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?></select><?php endif; ?></div></div>
  <div class="field"><label>GPS location</label><div class="geo-row"><input type="text" id="gps_lat" name="gps_lat" placeholder="Latitude"><input type="text" id="gps_lng" name="gps_lng" placeholder="Longitude"><button type="button" class="btn btn-outline" data-geo="gps_lat,gps_lng,geo_state"><?= icon('pin') ?>Detect</button></div><div class="geo-state" id="geo_state">Optional, but it helps teams plan the route.</div></div>
  <div class="field"><label for="description">Situation description</label><textarea id="description" name="description" maxlength="500" placeholder="Describe the situation, vulnerable groups, special requirements…"><?= old('description') ?></textarea></div>
  <button class="btn btn-primary btn-block" type="submit">Submit demand</button></form></section>
<?php dash_foot();
