<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['ngo']); $id = (int)$u['id'];

if (is_post()) {
    csrf_check();
    [$ok, $msg, $mid] = accept_demand($u, (int)post('demand_id'), (int)post('vehicle_id') ?: null, (int)post('driver_id') ?: null, mb_substr(post('notes'), 0, 300));
    flash($ok ? 'success' : 'error', $msg);
    redirect($ok ? 'dashboard/missions.php' : 'dashboard/available.php');
}

function demand_row(int $did): ?array {
    return row('SELECT d.*, z.name zone, z.lat zlat, z.lng zlng, di.name district FROM demands d JOIN zones z ON z.id=d.zone_id JOIN districts di ON di.id=d.district_id WHERE d.id=?', [$did]);
}
$accept = ($a = (int)($_GET['accept'] ?? 0)) ? demand_row($a) : null;
$view = ($v = (int)($_GET['view'] ?? 0)) ? demand_row($v) : null;

if ($accept || $view) {
    $d = $accept ?: $view; $mine = row("SELECT id FROM relief_missions WHERE demand_id=? AND ngo_id=? AND status='assigned'", [(int)$d['id'], $id]);
    $open = $d['status'] === 'pending' || ($d['status'] === 'matched' && $mine); $holder = zone_lock_holder((int)$d['zone_id'], $d['item_type']);
    $cand = inventory_candidates($d['item_type'], (int)$d['quantity'], (float)$d['zlat'], (float)$d['zlng'], $id); $best = $cand[0] ?? null;
    $vehicles = rows("SELECT * FROM vehicles WHERE ngo_id=? AND status<>'maintenance' ORDER BY plate", [$id]); $drivers = rows("SELECT * FROM team_members WHERE ngo_id=? AND is_driver=1 AND status='active' ORDER BY name", [$id]);
    dash_head($accept ? 'Accept Mission' : 'Demand Detail', 'available', $d['code']);
    ?>
    <div class="page-actions"><a href="<?= url('dashboard/available.php') ?>" style="font-size:14px">← Available demands</a></div>
    <?php if ($accept): ?><div class="lockbar"><?= icon('lock') ?><div><b>Zone lock notice</b>Accepting this mission locks <?= e($d['zone']) ?> for <?= e($d['item_type']) ?> to your team. No other NGO can receive an overlapping <?= e($d['item_type']) ?> mission for this zone until yours finishes or is released.</div></div><?php endif; ?>
    <div class="cols c32"><section class="panel"><header><h2>Mission Summary</h2><?= badge($d['priority']) ?></header>
      <div class="kv"><div><span>Demand ID</span><b><?= e($d['code']) ?></b></div><div><span>Zone</span><b><?= e($d['zone']) ?></b></div><div><span>Type</span><b><?= e(ucfirst($d['item_type'])) ?></b></div><div><span>Quantity</span><b><?= number_format($d['quantity']) ?> <?= e($d['unit']) ?></b></div><div><span>Households</span><b><?= (int)$d['households'] ?></b></div><div><span>Distance</span><b><?= $best && $best['distance'] !== null ? round($best['distance']) . ' km' : '—' ?></b></div><div><span>Priority</span><b><?= e(ucfirst($d['priority'])) ?></b></div><div><span>Access</span><b><?= e($d['access_mode']) ?></b></div><div><span>Contact</span><b><?= e($d['contact_name']) ?> · <?= e($d['contact_phone']) ?></b></div><div><span>Reported</span><b><?= ago($d['created_at']) ?></b></div></div>
      <?php if ($d['description']): ?><p style="padding:0 1.3rem 1rem;color:var(--muted);font-size:14px">“<?= e($d['description']) ?>”</p><?php endif; ?></section>
      <section class="panel"><header><h2>Your inventory match</h2></header><div class="pad"><?php if ($best): ?><div class="match <?= $best['sufficient'] ? '' : 'warn' ?>"><span><b><?= e($best['item']) ?></b><br><small>Available: <?= number_format($best['available']) ?> <?= e($best['unit']) ?> · <?= e($best['warehouse']) ?></small></span><b><?= $best['sufficient'] ? 'Sufficient' : 'Not enough' ?></b></div><?php else: ?><div class="match bad"><span>You have no available <?= e($d['item_type']) ?> stock.</span><b>No match</b></div><?php endif; ?></div></section></div>
    <?php if ($accept && $open && !$holder): ?><section class="panel"><header><h2>Assign vehicle &amp; driver</h2><span class="sub">You can change these when you dispatch</span></header>
      <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="demand_id" value="<?= (int)$d['id'] ?>">
        <div class="two"><div class="field"><label for="vehicle_id">Vehicle</label><select id="vehicle_id" name="vehicle_id"><option value="">Select later</option><?php foreach ($vehicles as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['plate'] . ' · ' . $x['type'] . ' (' . $x['capacity'] . ')' . ($x['status'] === 'on_mission' ? ' · on a mission' : '')) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="driver_id">Driver</label><select id="driver_id" name="driver_id"><option value="">Select later</option><?php foreach ($drivers as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option><?php endforeach; ?></select></div></div>
        <div class="field"><label for="notes">Additional notes</label><textarea id="notes" name="notes" maxlength="300" placeholder="Any special instructions for the field team…"></textarea></div>
        <div style="display:flex;gap:.7rem"><button class="btn btn-primary" <?= $best && $best['sufficient'] ? '' : 'disabled' ?>>Accept mission &amp; lock zone</button><a class="btn btn-outline" href="<?= url('dashboard/available.php') ?>">Cancel</a></div></form></section>
    <?php elseif ($view && $open && !$holder): ?><div><a class="btn btn-primary" href="?accept=<?= (int)$d['id'] ?>">Accept mission</a></div>
    <?php else: ?><div class="alert amber"><span><b><?= $holder ? 'Zone locked by ' . e($holder['organization']) : 'This demand is no longer open.' ?></b></span></div><?php endif;
    if ($best && $best['wlat'] !== null): ?><section class="panel"><header><h2>Route</h2><span class="sub"><?= e($best['warehouse']) ?> → <?= e($d['zone']) ?></span></header><div class="mapbox route" data-route='<?= e(json_encode(['from' => ['lat' => $best['wlat'], 'lng' => $best['wlng'], 'label' => $best['warehouse']], 'to' => ['lat' => $d['zlat'], 'lng' => $d['zlng'], 'label' => $d['zone']], 'progress' => 0])) ?>'></div></section><?php endif;
    dash_foot(); exit;
}

$pr = $_GET['priority'] ?? ''; $qs = trim((string)($_GET['q'] ?? ''));
$rowsD = rows("SELECT d.*, z.name zone, z.lat zlat, z.lng zlng, di.name district FROM demands d JOIN zones z ON z.id=d.zone_id JOIN districts di ON di.id=d.district_id WHERE d.status='pending'" . (in_array($pr, PRIORITIES, true) ? ' AND d.priority=' . db()->quote($pr) : '') . ($qs !== '' ? ' AND (z.name LIKE ' . db()->quote("%$qs%") . ' OR di.name LIKE ' . db()->quote("%$qs%") . ')' : ''));
foreach ($rowsD as &$d) { $d['best'] = inventory_candidates($d['item_type'], (int)$d['quantity'], (float)$d['zlat'], (float)$d['zlng'], $id)[0] ?? null; $d['holder'] = zone_lock_holder((int)$d['zone_id'], $d['item_type']); } unset($d);
usort($rowsD, fn($a, $b) => [(bool)$a['holder'], array_search($a['priority'], PRIORITIES), $a['best']['distance'] ?? 9e9] <=> [(bool)$b['holder'], array_search($b['priority'], PRIORITIES), $b['best']['distance'] ?? 9e9]);
$free = count(array_filter($rowsD, fn($d) => !$d['holder']));
dash_head('Available Demands', 'available', $free . ' open demand' . ($free === 1 ? '' : 's') . ' you can take');
?>
<div class="alert blue"><span><b>Matching is based on your inventory and location.</b><span class="sub">Demands are sorted by priority and distance from your nearest warehouse. Accepting a demand locks the zone for that item.</span></span></div>
<form class="filter-bar" method="get"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search zone or district"><select name="priority" onchange="this.form.submit()"><option value="">Any priority</option><?= opts(PRIORITIES, $pr) ?></select><button class="btn btn-outline btn-sm">Search</button></form>
<div class="dem-grid"><?php foreach ($rowsD as $d): $b = $d['best']; ?>
  <article class="dem-card <?= $d['holder'] ? 'locked' : '' ?>"><header><div><h3><?= e($d['zone']) ?></h3><small><?= e($d['district']) ?><?= $b && $b['distance'] !== null ? ' · ' . round($b['distance']) . ' km from ' . e($b['warehouse']) : '' ?></small></div><div class="tags"><?= badge($d['priority']) ?><span class="chip"><?= e(ucfirst($d['item_type'])) ?></span></div></header>
    <div class="nums"><div><b><?= number_format($d['quantity']) ?></b><?= e($d['unit']) ?></div><div><b><?= (int)$d['households'] ?></b>Households</div><div><b style="font-size:.9rem"><?= e($d['access_mode']) ?></b>Access</div></div>
    <?php if ($d['holder']): ?><div class="match bad"><span>🔒 Locked by <?= e($d['holder']['organization']) ?></span></div>
    <?php elseif ($b): ?><div class="match <?= $b['sufficient'] ? '' : 'warn' ?>"><span><?= e($b['item']) ?>: <?= number_format($b['available']) ?> available</span><b><?= $b['sufficient'] ? 'Sufficient' : 'Partial' ?></b></div>
    <?php else: ?><div class="match bad"><span>No available <?= e($d['item_type']) ?> stock</span><b>No match</b></div><?php endif; ?>
    <div class="meta"><span>Contact: <?= e($d['contact_name']) ?> · <?= e($d['contact_phone']) ?></span><span>Declared <?= ago($d['created_at']) ?></span></div>
    <div class="btns"><a class="btn btn-outline" href="?view=<?= (int)$d['id'] ?>">View details</a><?php if (!$d['holder'] && $b && $b['sufficient']): ?><a class="btn btn-primary" href="?accept=<?= (int)$d['id'] ?>">Accept mission</a><?php else: ?><span class="btn btn-outline" style="opacity:.5;cursor:not-allowed"><?= $d['holder'] ? 'Zone locked' : 'Restock to accept' ?></span><?php endif; ?></div></article>
<?php endforeach; ?></div>
<?php if (!$rowsD): ?><div class="panel"><div class="empty">No pending demands match your filters.</div></div><?php endif;
dash_foot();
