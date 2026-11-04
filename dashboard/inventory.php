<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'ngo']);
$manage = $u['role'] === 'ngo';

if (is_post()) {
    csrf_check();
    if (!$manage) { flash('error', 'Only NGO / Volunteer teams manage stock.'); redirect('dashboard/inventory.php'); }
    $a = post('action');
    if ($a === 'add') {
        $item = post('item'); $cat = post('category'); $qty = (int)post('quantity'); $wh = post('warehouse'); $dist = (int)post('district_id') ?: null;
        if ($item === '' || $wh === '') flash('error', 'Enter the item name and warehouse.');
        elseif (!in_array($cat, ITEM_TYPES, true)) flash('error', 'Choose a category.');
        elseif ($qty < 0) flash('error', 'Quantity cannot be negative.');
        elseif (!$dist) flash('error', 'Choose the district where this warehouse is. Matching uses it to find the nearest stock.');
        else {
            q('INSERT INTO inventory (ngo_id,item,category,quantity,unit,warehouse,district_id,low_threshold) VALUES (?,?,?,?,?,?,?,?)', [(int)$u['id'], $item, $cat, $qty, post('unit', 'units') ?: 'units', $wh, $dist, max(0, (int)post('low_threshold', '50'))]);
            audit('inventory.add', 'inventory', (int)db()->lastInsertId(), "$item × $qty"); flash('success', 'Stock item added.');
        }
    } else {
        $id = (int)post('id'); $it = row('SELECT * FROM inventory WHERE id=? AND ngo_id=?', [$id, (int)$u['id']]);
        if (!$it) flash('error', 'Item not found in your inventory.');
        elseif ($a === 'update') {
            $qty = (int)post('quantity');
            if ($qty < (int)$it['reserved']) flash('error', 'You cannot set the total below the ' . number_format($it['reserved']) . ' units reserved for accepted missions.');
            else { q('UPDATE inventory SET quantity=?, low_threshold=? WHERE id=?', [$qty, max(0, (int)post('low_threshold')), $id]); audit('inventory.update', 'inventory', $id, $it['item'] . ': ' . $it['quantity'] . ' → ' . $qty); check_low_stock($id); flash('success', 'Stock updated.'); }
        } elseif ($a === 'restock') {
            $add = (int)post('add');
            if ($add < 1) flash('error', 'Enter how many units arrived.'); else { q('UPDATE inventory SET quantity=quantity+? WHERE id=?', [$add, $id]); audit('inventory.restock', 'inventory', $id, $it['item'] . ' +' . $add); flash('success', number_format($add) . ' units added.'); }
        } elseif ($a === 'delete') { if ($it['reserved'] > 0) flash('error', 'This stock is reserved for an accepted mission.'); else { q('UPDATE relief_missions SET inventory_id=NULL WHERE inventory_id=?', [$id]); q('DELETE FROM inventory WHERE id=?', [$id]); audit('inventory.delete', 'inventory', $id, $it['item']); flash('success', 'Item removed.'); } }
    }
    redirect('dashboard/inventory.php');
}
$w = $manage ? ' WHERE i.ngo_id=?' : ''; $p = $manage ? [(int)$u['id']] : [];
$list = rows("SELECT i.*, u.organization, d.name AS wdistrict FROM inventory i JOIN users u ON u.id=i.ngo_id LEFT JOIN districts d ON d.id=i.district_id$w ORDER BY (i.quantity-i.reserved<=i.low_threshold) DESC, u.organization, i.category, i.item", $p);
$low = count(array_filter($list, fn($i) => $i['quantity'] - $i['reserved'] <= $i['low_threshold']));
dash_head($manage ? 'Inventory Management' : 'Inventory', 'inventory', $manage ? 'Publish stock so demands are matched to the nearest source' : 'Stock published by every NGO team (read-only)');
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)"><?= kpi('', 'Total items', count($list), 'Stock lines', 'box', 'b') . kpi($low ? 'red' : 'green', 'Low stock alerts', $low, $low ? 'Available at or below alert level' : 'All stock adequate', 'alert', $low ? 'r' : 'g') . kpi('amber', 'Reserved', array_sum(array_column($list, 'reserved')), 'Held for accepted missions', 'lock', 'a') ?></div>
<?php if ($manage): ?><details class="panel"><summary>Add stock</summary>
  <form method="post" class="pad"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="two"><div class="field"><label for="item">Item</label><input type="text" id="item" name="item" required maxlength="120" placeholder="e.g. Dry food package (family)"></div><div class="field"><label for="warehouse">Warehouse</label><input type="text" id="warehouse" name="warehouse" required maxlength="120" placeholder="e.g. Sylhet Central Warehouse"></div></div>
    <div class="three" style="grid-template-columns:repeat(5,1fr)"><div class="field"><label for="district_id">Warehouse district</label><select id="district_id" name="district_id" required><?= district_options() ?></select></div><div class="field"><label for="category">Category</label><select id="category" name="category"><?= opts(ITEM_TYPES) ?></select></div><div class="field"><label for="quantity">Quantity</label><input type="number" id="quantity" name="quantity" min="0" value="0"></div><div class="field"><label for="unit">Unit</label><input type="text" id="unit" name="unit" value="packs" maxlength="30"></div><div class="field"><label for="low_threshold">Alert below</label><input type="number" id="low_threshold" name="low_threshold" min="0" value="50"></div></div>
    <button class="btn btn-primary" type="submit">Add to inventory</button></form></details><?php endif; ?>
<section class="panel"><header><h2><?= $manage ? 'Inventory: ' . e($u['organization']) : 'All inventory' ?></h2><span class="sub"><?= count($list) ?> items</span></header><table class="tbl"><thead><tr><th>Item</th><?php if (!$manage): ?><th>Team</th><?php endif; ?><th>Category</th><th class="num">Total</th><th class="num">Reserved</th><th>Available</th><th>Status</th><th>Warehouse</th><?php if ($manage): ?><th style="text-align:right">Update</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($list as $i): $av = $i['quantity'] - $i['reserved']; $isLow = $av <= $i['low_threshold']; ?>
  <tr><td><b><?= e($i['item']) ?></b><small>Updated <?= ago($i['updated_at']) ?></small></td><?php if (!$manage): ?><td><?= e($i['organization']) ?></td><?php endif; ?><td><span class="chip"><?= e(ucfirst($i['category'])) ?></span></td>
    <td class="num"><?= number_format($i['quantity']) ?></td><td class="num" style="color:#A56F00"><?= number_format($i['reserved']) ?></td>
    <td class="prog"><b class="<?= $isLow ? 'low-stock' : '' ?>"><?= number_format($av) ?> <small style="display:inline"><?= e($i['unit']) ?></small></b><div class="bar" style="height:6px"><i style="width:<?= pct($av, $i['quantity']) ?>%;<?= $isLow ? 'background:var(--red)' : 'background:var(--green)' ?>"></i></div></td>
    <td><?= $isLow ? '<span class="badge b-red">Low stock</span>' : '<span class="badge b-green">Adequate</span>' ?><small>alert at <?= number_format($i['low_threshold']) ?></small></td><td><?= e($i['warehouse']) ?><small><?= e($i['wdistrict'] ?: '') ?></small></td>
    <?php if ($manage): ?><td><div class="actions" style="flex-wrap:wrap"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restock"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input type="number" name="add" min="1" placeholder="+ qty" style="width:80px" aria-label="Units received"><button class="btn btn-primary btn-xs">Restock</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input type="number" name="quantity" min="0" value="<?= (int)$i['quantity'] ?>" aria-label="Total quantity"><input type="number" name="low_threshold" min="0" value="<?= (int)$i['low_threshold'] ?>" aria-label="Alert below" style="width:70px"><button class="btn btn-outline btn-xs">Edit</button></form>
      <form method="post" data-confirm="Remove this item?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-outline btn-xs" style="color:var(--red-d)">Remove</button></form></div></td><?php endif; ?></tr>
<?php endforeach; if (!$list) echo empty_row($manage ? 8 : 8, $manage ? 'Your inventory is empty. Add your first stock item above.' : 'No NGO has published stock yet.'); ?></tbody></table></section>
<?php dash_foot();
