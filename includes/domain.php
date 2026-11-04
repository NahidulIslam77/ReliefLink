<?php
/* Domain logic shared by dashboard pages and the sync API:
   geo helpers, codes, zone locking, matchmaking, mission lifecycle, evidence uploads, verification. */

const PRIORITIES = ['critical', 'high', 'medium', 'low'];
const ACCESS_MODES = ['Road', 'Road + Boat', 'Boat only', 'Air / helicopter', 'On foot'];
const EVIDENCE_RADIUS_KM = 10;
const DEMAND_UNITS = ['food' => 'packages', 'water' => 'jerry cans', 'medicine' => 'kits', 'shelter' => 'tarps', 'hygiene' => 'kits', 'clothing' => 'sets', 'rescue' => 'boats', 'other' => 'units'];
const SOS_TYPES = ['medical' => 'Medical emergency', 'rescue' => 'Rescue needed', 'fire' => 'Fire', 'flooding' => 'Sudden flooding', 'food' => 'Severe food shortage', 'other' => 'Other emergency'];

function haversine(float $a1, float $o1, float $a2, float $o2): float {
    $dl = deg2rad($a2 - $a1); $dn = deg2rad($o2 - $o1);
    $h = sin($dl / 2) ** 2 + cos(deg2rad($a1)) * cos(deg2rad($a2)) * sin($dn / 2) ** 2;
    return 2 * 6371 * asin(min(1, sqrt($h)));
}
function in_bangladesh($lat, $lng): bool { return is_numeric($lat) && is_numeric($lng) && $lat >= 20.5 && $lat <= 26.7 && $lng >= 88.0 && $lng <= 92.8; }
function demand_code(int $id): string { return 'DEM-' . date('Y') . '-' . (1000 + $id); }
function mission_code(int $id): string { return 'RL-' . date('Y') . '-' . (1100 + $id); }
function confirm_code(): string { return (string)random_int(100000, 999999); }
function dt_label(?string $dt): string { return $dt ? date('j M, H:i', strtotime($dt)) : '—'; }

function mission_event(int $mid, string $status, string $note = '', ?float $lat = null, ?float $lng = null): void {
    q('INSERT INTO mission_events (mission_id,status,note,user_id,lat,lng) VALUES (?,?,?,?,?,?)', [$mid, $status, mb_substr($note, 0, 250), user()['id'] ?? null, $lat, $lng]);
}

/* ---------- zone locking (one NGO per zone + item type at a time) ---------- */
function zone_lock_holder(int $zoneId, string $item): ?array {
    return row('SELECT l.*, u.organization, m.title AS mtitle, m.code AS mcode FROM zone_locks l JOIN users u ON u.id=l.ngo_id JOIN relief_missions m ON m.id=l.mission_id WHERE l.zone_id=? AND l.item_type=? AND l.active_key=1', [$zoneId, $item]);
}
function try_lock_zone(int $zoneId, string $item, int $ngoId, int $missionId): bool {
    try { q('INSERT INTO zone_locks (zone_id,item_type,ngo_id,mission_id,active_key) VALUES (?,?,?,?,1)', [$zoneId, $item, $ngoId, $missionId]); return true; }
    catch (PDOException $e) { if ($e->getCode() === '23000') return false; throw $e; }
}

/* ---------- lookups ---------- */
function find_coordinator(?int $zoneId, int $districtId): ?int {
    if ($zoneId) { $id = val("SELECT id FROM users WHERE role='coordinator' AND status='active' AND zone_id=? LIMIT 1", [$zoneId]); if ($id) return (int)$id; }
    $id = val("SELECT id FROM users WHERE role='coordinator' AND status='active' AND district_id=? ORDER BY id LIMIT 1", [$districtId]);
    return $id ? (int)$id : null;
}
function resolve_disaster(array $zone): ?int {
    if ($zone['disaster_id'] && val("SELECT COUNT(*) FROM disasters WHERE id=? AND status<>'resolved'", [(int)$zone['disaster_id']])) return (int)$zone['disaster_id'];
    $id = val("SELECT id FROM disasters WHERE district_id=? AND status<>'resolved' ORDER BY declared_at DESC LIMIT 1", [(int)$zone['district_id']]);
    return $id ? (int)$id : null;
}

/* ---------- matchmaking: rank NGO stock against a demand ---------- */
function inventory_candidates(string $item, int $qty, ?float $lat, ?float $lng, ?int $ngoId = null): array {
    $sql = "SELECT i.id inv_id, i.ngo_id, i.item, i.unit, i.quantity - i.reserved AS available, i.warehouse, u.organization, u.first_name, w.lat wlat, w.lng wlng, w.name wdistrict
            FROM inventory i JOIN users u ON u.id=i.ngo_id AND u.status='active' LEFT JOIN districts w ON w.id=i.district_id
            WHERE i.category=? AND i.quantity - i.reserved > 0" . ($ngoId ? ' AND i.ngo_id=?' : '');
    $out = [];
    foreach (rows($sql, $ngoId ? [$item, $ngoId] : [$item]) as $r) {
        $r['available'] = (int)$r['available'];
        $r['distance'] = ($lat !== null && $lng !== null && $r['wlat'] !== null) ? haversine($lat, $lng, (float)$r['wlat'], (float)$r['wlng']) : null;
        $r['sufficient'] = $r['available'] >= $qty;
        $out[] = $r;
    }
    usort($out, fn($a, $b) => [$b['sufficient'], $a['distance'] ?? 9e9, -$a['available']] <=> [$a['sufficient'], $b['distance'] ?? 9e9, -$b['available']]);
    if (!$ngoId) { $seen = []; $out = array_values(array_filter($out, function ($r) use (&$seen) { if (isset($seen[$r['ngo_id']])) return false; return $seen[$r['ngo_id']] = true; })); }
    return $out;
}
function check_low_stock(int $invId): void {
    $i = row('SELECT * FROM inventory WHERE id=?', [$invId]);
    if (!$i || $i['quantity'] - $i['reserved'] > $i['low_threshold']) return;
    $body = $i['item'] . ' has ' . number_format($i['quantity'] - $i['reserved']) . ' available, at or below your ' . number_format($i['low_threshold']) . ' alert level.';
    if (!val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND body=? AND created_at > NOW() - INTERVAL 1 DAY", [(int)$i['ngo_id'], $body]))
        notify_user((int)$i['ngo_id'], 'Inventory alert: low stock', $body, 'inventory.php', 'inventory');
}

/* ---------- accept a demand: lock the zone, reserve stock, create/advance the mission (atomic) ---------- */
function accept_demand(array $u, int $demandId, ?int $vehicleId, ?int $driverId, string $notes): array {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $d = row('SELECT * FROM demands WHERE id=? FOR UPDATE', [$demandId]);
        if (!$d) throw new RuntimeException('Demand not found.');
        $pre = row("SELECT * FROM relief_missions WHERE demand_id=? AND status='assigned' AND ngo_id=?", [$demandId, (int)$u['id']]);
        if (!($d['status'] === 'pending' || ($d['status'] === 'matched' && $pre))) throw new RuntimeException('This demand is no longer open.');
        if ($vehicleId && !val("SELECT COUNT(*) FROM vehicles WHERE id=? AND ngo_id=? AND status<>'maintenance'", [$vehicleId, (int)$u['id']])) throw new RuntimeException('Choose one of your own vehicles that is not in maintenance.');
        if ($driverId && !val("SELECT COUNT(*) FROM team_members WHERE id=? AND ngo_id=? AND is_driver=1 AND status='active'", [$driverId, (int)$u['id']])) throw new RuntimeException('Choose an active driver from your team.');
        $zone = row('SELECT * FROM zones WHERE id=?', [(int)$d['zone_id']]);
        $best = null;
        foreach (inventory_candidates($d['item_type'], (int)$d['quantity'], $zone ? (float)$zone['lat'] : null, $zone ? (float)$zone['lng'] : null, (int)$u['id']) as $c) if ($c['sufficient']) { $best = $c; break; }
        if (!$best) throw new RuntimeException('Your available ' . $d['item_type'] . ' stock is below the ' . number_format($d['quantity']) . ' requested. Restock first.');
        $co = find_coordinator((int)$d['zone_id'], (int)$d['district_id']);
        $title = ucfirst($d['item_type']) . ' · ' . ($zone['name'] ?? 'Zone');
        if ($pre) {
            $mid = (int)$pre['id'];
            q("UPDATE relief_missions SET status='accepted', inventory_id=?, vehicle_id=?, driver_id=?, coordinator_id=COALESCE(coordinator_id,?), notes=?, reserved_qty=? WHERE id=?", [$best['inv_id'], $vehicleId, $driverId, $co, $notes ?: null, (int)$d['quantity'], $mid]);
        } else {
            q("INSERT INTO relief_missions (disaster_id,title,item_type,unit,target_qty,budget,status,ngo_id,coordinator_id,created_by,demand_id,zone_id,inventory_id,vehicle_id,driver_id,notes,priority,reserved_qty) VALUES (?,?,?,?,?,0,'accepted',?,?,?,?,?,?,?,?,?,?,?)",
              [(int)$d['disaster_id'], $title, $d['item_type'], $d['unit'], (int)$d['quantity'], (int)$u['id'], $co, (int)$u['id'], $demandId, (int)$d['zone_id'], $best['inv_id'], $vehicleId, $driverId, $notes ?: null, $d['priority'], (int)$d['quantity']]);
            $mid = (int)$pdo->lastInsertId();
            q('UPDATE relief_missions SET code=? WHERE id=?', [mission_code($mid), $mid]);
        }
        if (!try_lock_zone((int)$d['zone_id'], $d['item_type'], (int)$u['id'], $mid)) {
            $h = zone_lock_holder((int)$d['zone_id'], $d['item_type']);
            throw new RuntimeException('Another team already holds this zone for ' . $d['item_type'] . ($h ? ' (' . $h['organization'] . ')' : '') . '. Pick a different demand.');
        }
        q('UPDATE inventory SET reserved = reserved + ? WHERE id=?', [(int)$d['quantity'], $best['inv_id']]);
        q("UPDATE demands SET status='matched' WHERE id=?", [$demandId]);
        mission_event($mid, 'accepted', 'Mission accepted and zone locked for ' . $d['item_type']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e instanceof RuntimeException ? $e->getMessage() : 'Could not accept the mission. Please try again.', 0];
    }
    audit('mission.accept', 'mission', $mid, $title . ' · zone locked');
    if ($co) notify_user($co, 'Mission accepted for your zone', $title . ' (' . $u['organization'] . ')', 'missions.php', 'mission');
    notify_where("role='district_admin' AND district_id=?", [(int)$d['district_id']], 'Zone locked', $title . ' is now held by ' . ($u['organization'] ?: 'an NGO team'), 'ngos.php', 'mission');
    check_low_stock((int)$best['inv_id']);
    return [true, 'Mission accepted. The zone is now locked to your team.', $mid];
}

/* ---------- finishing / cancelling a mission frees the lock, reserved stock and vehicle ---------- */
function close_mission(int $mid, string $status, string $note = ''): void {
    $m = row('SELECT * FROM relief_missions WHERE id=?', [$mid]);
    if (!$m || in_array($m['status'], ['completed', 'cancelled'], true)) return;
    q('UPDATE relief_missions SET status=?, reserved_qty=0 WHERE id=?', [$status, $mid]);
    q('UPDATE zone_locks SET active_key=NULL, released_at=NOW() WHERE mission_id=? AND active_key=1', [$mid]);
    if ($m['inventory_id'] && $m['reserved_qty'] > 0) q('UPDATE inventory SET reserved=GREATEST(0,reserved-?) WHERE id=?', [(int)$m['reserved_qty'], (int)$m['inventory_id']]);
    if ($m['vehicle_id']) q("UPDATE vehicles SET status='available' WHERE id=? AND status='on_mission'", [(int)$m['vehicle_id']]);
    if ($m['demand_id']) q("UPDATE demands SET status=? WHERE id=? AND status NOT IN ('delivered','cancelled')", [$status === 'completed' ? 'delivered' : 'pending', (int)$m['demand_id']]);
    mission_event($mid, $status, $note ?: ($status === 'completed' ? 'Mission completed and verified' : 'Mission cancelled'));
}

/* ---------- photo evidence ---------- */
function save_evidence(array $f): array {
    $names = $f['name'] ?? []; $out = []; $dir = __DIR__ . '/../uploads/evidence';
    if (!is_array($names)) return [[], 'Attach at least 3 photos of the distribution.'];
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fail = function (string $msg) use (&$out) { foreach ($out as $p) @unlink(__DIR__ . '/../' . $p); return [[], $msg]; };
    foreach ($names as $i => $n) {
        $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK || $f['size'][$i] > 6 * 1024 * 1024) return $fail('Each photo must upload successfully and be under 6 MB.');
        $info = @getimagesize($f['tmp_name'][$i]);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext) return $fail('Only JPG, PNG or WebP photos are accepted.');
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], "$dir/$name")) return $fail('Could not store a photo. Check folder permissions on uploads/.');
        $out[] = 'uploads/evidence/' . $name;
    }
    if (count($out) < 3) return $fail('Attach at least 3 photos of the distribution.');
    if (count($out) > 8) return $fail('Attach at most 8 photos.');
    return [$out, ''];
}

/* ---------- NGO submits delivery evidence ---------- */
function submit_delivery(array $u, array $in, array $files): array {
    $m = row("SELECT m.*, z.lat zlat, z.lng zlng FROM relief_missions m LEFT JOIN zones z ON z.id=m.zone_id WHERE m.id=? AND m.ngo_id=? AND m.status IN ('in_transit','arrived')", [(int)($in['mission_id'] ?? 0), (int)$u['id']]);
    $qty = (int)($in['quantity'] ?? 0); $fam = (int)($in['recipients'] ?? 0); $lat = $in['gps_lat'] ?? ''; $lng = $in['gps_lng'] ?? '';
    if (!$m) return [false, 'Choose one of your dispatched missions.'];
    if (!hash_equals((string)$m['confirmation_code'], preg_replace('/\D/', '', (string)($in['code'] ?? '')))) return [false, 'That confirmation code does not match this mission. Ask the Spot Coordinator for the 6-digit code.'];
    if ($qty < 1 || $fam < 1) return [false, 'Quantity and families served must be at least 1.'];
    $pending = (int)val("SELECT COALESCE(SUM(quantity),0) FROM deliveries WHERE mission_id=? AND status='pending'", [(int)$m['id']]);
    $left = (int)$m['target_qty'] - (int)$m['delivered_qty'] - $pending;
    if ($qty > $left) return [false, 'That is more than the quantity still outstanding (' . number_format(max(0, $left)) . ' ' . $m['unit'] . ').'];
    if (!in_bangladesh($lat, $lng)) return [false, 'Capture the GPS location of the delivery point (tap "Detect location").'];
    if (empty($in['agree'])) return [false, 'Tick the box to confirm the information is accurate.'];
    [$paths, $err] = save_evidence($files);
    if ($err) return [false, $err];
    $loc = trim((string)($in['location'] ?? '')) ?: ($m['title']);
    q('INSERT INTO deliveries (mission_id,reported_by,quantity,recipients,location,notes,gps_lat,gps_lng,evidence_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [(int)$m['id'], (int)$u['id'], $qty, $fam, mb_substr($loc, 0, 200), mb_substr(trim((string)($in['notes'] ?? '')), 0, 500) ?: null, (float)$lat, (float)$lng]);
    $id = (int)db()->lastInsertId();
    foreach ($paths as $p) q('INSERT INTO delivery_photos (delivery_id,path) VALUES (?,?)', [$id, $p]);
    if ($m['status'] === 'in_transit') q("UPDATE relief_missions SET status='arrived', arrived_at=COALESCE(arrived_at,NOW()), progress=100 WHERE id=?", [(int)$m['id']]);
    mission_event((int)$m['id'], 'awaiting_confirmation', number_format($qty) . ' ' . $m['unit'] . ' reported delivered; awaiting audit', (float)$lat, (float)$lng);
    audit('delivery.submit', 'delivery', $id, $m['title'] . ' · ' . $qty . ' ' . $m['unit'] . ' · ' . count($paths) . ' photos');
    notify_where("role='audit'", [], 'Delivery awaiting verification', ($m['code'] ?: $m['title']) . ' · ' . number_format($qty) . ' ' . $m['unit'] . ' (' . ucfirst($m['priority']) . ' priority)', 'deliveries.php', 'verification');
    if ($m['coordinator_id']) notify_user((int)$m['coordinator_id'], 'Delivery evidence submitted', $m['title'], 'confirm.php', 'mission');
    return [true, 'Evidence submitted. The Audit Wing will review it before it counts toward totals.'];
}

/* ---------- Spot Coordinator confirms receipt ---------- */
function coordinator_confirm(array $u, array $in): array {
    $m = row("SELECT * FROM relief_missions WHERE id=? AND coordinator_id=? AND status IN ('in_transit','arrived')", [(int)($in['mission_id'] ?? 0), (int)$u['id']]);
    $fam = (int)($in['families'] ?? 0);
    if (!$m) return [false, 'Choose one of your active missions.'];
    if ($fam < 1) return [false, 'Enter the number of families actually served.'];
    if (empty($in['agree'])) return [false, 'Tick the box to confirm the delivery details are accurate.'];
    q('UPDATE relief_missions SET coord_confirmed=1, coord_families=?, coord_notes=?, coord_confirmed_at=NOW() WHERE id=?', [$fam, mb_substr(trim((string)($in['notes'] ?? '')), 0, 300) ?: null, (int)$m['id']]);
    mission_event((int)$m['id'], 'coordinator_confirmed', $fam . ' families confirmed by the Spot Coordinator');
    audit('mission.coordinator_confirm', 'mission', (int)$m['id'], $fam . ' families');
    if ($m['ngo_id']) notify_user((int)$m['ngo_id'], 'Coordinator confirmed receipt', $m['title'] . ' · ' . $fam . ' families', 'confirm.php', 'mission');
    return [true, 'Delivery confirmed. The NGO team can now submit evidence with the code.'];
}

/* ---------- demand + SOS creation (shared by forms and the offline sync API) ---------- */
function create_demand(array $u, array $in): array {
    $uid = trim((string)($in['client_uid'] ?? '')) ?: null;
    if ($uid && val('SELECT COUNT(*) FROM demands WHERE client_uid=?', [$uid])) return [true, 'Already received.', (int)val('SELECT id FROM demands WHERE client_uid=?', [$uid])];
    $zoneId = (int)($in['zone_id'] ?? 0) ?: (int)$u['zone_id'];
    $zone = row('SELECT * FROM zones WHERE id=? AND district_id=?', [$zoneId, (int)$u['district_id']]);
    $item = (string)($in['item_type'] ?? ''); $qty = (int)($in['quantity'] ?? 0); $hh = (int)($in['households'] ?? 0); $pri = (string)($in['priority'] ?? 'high');
    if (!$zone) return [false, 'Choose a zone in your district.', 0];
    if (!in_array($item, ITEM_TYPES, true)) return [false, 'Choose a relief category.', 0];
    if ($qty < 1 || $qty > 1000000) return [false, 'Enter the quantity needed.', 0];
    if ($hh < 1) return [false, 'Enter how many households are affected.', 0];
    if (!in_array($pri, PRIORITIES, true)) $pri = 'high';
    $did = resolve_disaster($zone);
    if (!$did) return [false, 'No active disaster is declared for this district yet. Ask the District Admin.', 0];
    $acc = in_array($in['access_mode'] ?? '', ACCESS_MODES, true) ? $in['access_mode'] : 'Road';
    $lat = in_bangladesh($in['gps_lat'] ?? '', $in['gps_lng'] ?? '') ? (float)$in['gps_lat'] : null; $lng = $lat !== null ? (float)$in['gps_lng'] : null;
    $unit = trim((string)($in['unit'] ?? '')) ?: DEMAND_UNITS[$item];
    q('INSERT INTO demands (zone_id,disaster_id,district_id,item_type,quantity,unit,households,access_mode,priority,contact_name,contact_phone,description,lat,lng,reported_by,client_uid) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [(int)$zone['id'], $did, (int)$zone['district_id'], $item, $qty, mb_substr($unit, 0, 30), $hh, $acc, $pri, $u['first_name'] . ' ' . $u['last_name'], $u['phone'], mb_substr(trim((string)($in['description'] ?? '')), 0, 500) ?: null, $lat, $lng, (int)$u['id'], $uid]);
    $id = (int)db()->lastInsertId();
    q('UPDATE demands SET code=? WHERE id=?', [demand_code($id), $id]);
    audit('demand.submit', 'demand', $id, ucfirst($item) . ' ' . $qty . ' · ' . $zone['name']);
    $msg = demand_code($id) . ' · ' . number_format($qty) . ' ' . $unit . ' of ' . $item . ' in ' . $zone['name'];
    notify_where("role='district_admin' AND district_id=?", [(int)$zone['district_id']], 'New ' . $pri . ' demand', $msg, 'demands.php', 'demand');
    if ($pri === 'critical') notify_where("role='super_admin'", [], 'Critical demand reported', $msg, 'demands.php', 'demand');
    return [true, 'Demand ' . demand_code($id) . ' submitted. The District Admin and matching service can see it now.', $id];
}

function create_sos_alert(array $u, array $in): array {
    $uid = trim((string)($in['client_uid'] ?? '')) ?: null;
    if ($uid && val('SELECT COUNT(*) FROM sos_requests WHERE client_uid=?', [$uid])) return [true, 'Already received.', 0];
    $type = (string)($in['need_type'] ?? ''); $desc = trim((string)($in['description'] ?? '')); $sev = in_array($in['severity'] ?? '', ['critical', 'high', 'medium'], true) ? $in['severity'] : 'critical';
    $phone = preg_replace('/[\s-]/', '', (string)($in['phone'] ?? '')) ?: $u['phone'];
    if (!isset(SOS_TYPES[$type])) return [false, 'Choose the emergency type.', 0];
    if ($desc === '') return [false, 'Describe the emergency so responders know what to bring.', 0];
    if (!preg_match('/^(?:\+?88)?(01[3-9]\d{8})$/', $phone, $m)) return [false, 'Enter a valid contact number (01XXXXXXXXX).', 0]; $phone = $m[1];
    $zone = $u['zone_id'] ? row('SELECT name FROM zones WHERE id=?', [(int)$u['zone_id']]) : null;
    $lat = in_bangladesh($in['gps_lat'] ?? '', $in['gps_lng'] ?? '') ? (float)$in['gps_lat'] : null; $lng = $lat !== null ? (float)$in['gps_lng'] : null;
    q("INSERT INTO sos_requests (name,phone,district_id,location,people_count,need_type,description,source,severity,lat,lng,reported_by,client_uid) VALUES (?,?,?,?,?,?,?,'coordinator',?,?,?,?,?)",
      [$u['first_name'] . ' ' . $u['last_name'], $phone, (int)$u['district_id'], ($zone['name'] ?? $u['district_name']) . ($lat !== null ? '' : ''), max(1, min(5000, (int)($in['people_count'] ?? 1))), $type, mb_substr($desc, 0, 1000), $sev, $lat, $lng, (int)$u['id'], $uid]);
    $id = (int)db()->lastInsertId();
    audit('sos.alert', 'sos', $id, SOS_TYPES[$type] . ' · ' . ($zone['name'] ?? ''));
    $msg = SOS_TYPES[$type] . ' at ' . ($zone['name'] ?? $u['district_name']) . ': ' . mb_substr($desc, 0, 120);
    notify_where("(role='district_admin' AND district_id=?) OR role='super_admin'", [(int)$u['district_id']], 'SOS alert (' . $sev . ')', $msg, 'sos.php', 'sos');
    return [true, 'SOS alert sent. Your District Admin and nearby teams have been notified.', $id];
}

/* ---------- Audit Wing decision (atomic: totals, stock and mission state change together) ---------- */
function review_delivery(int $id, string $decision, array $u, string $note): array {
    $pdo = db(); $pdo->beginTransaction(); $after = null;
    try {
        $d = row('SELECT dl.*, m.title, m.code mcode, m.unit, m.inventory_id FROM deliveries dl JOIN relief_missions m ON m.id=dl.mission_id WHERE dl.id=? FOR UPDATE', [$id]);
        if (!$d || $d['status'] !== 'pending') throw new RuntimeException('This delivery has already been reviewed.');
        q('UPDATE deliveries SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?', [$decision === 'verify' ? 'verified' : 'rejected', (int)$u['id'], $note ?: null, $id]);
        if ($decision === 'verify') {
            $mid = (int)$d['mission_id']; $qty = (int)$d['quantity'];
            q('UPDATE relief_missions SET delivered_qty = LEAST(target_qty, delivered_qty + ?) WHERE id=?', [$qty, $mid]);
            $m = row('SELECT * FROM relief_missions WHERE id=?', [$mid]);
            if ($m['inventory_id'] && $m['reserved_qty'] > 0) {
                $take = min($qty, (int)$m['reserved_qty']);
                q('UPDATE inventory SET quantity=GREATEST(0,quantity-?), reserved=GREATEST(0,reserved-?) WHERE id=?', [$take, $take, (int)$m['inventory_id']]);
                q('UPDATE relief_missions SET reserved_qty=reserved_qty-? WHERE id=?', [$take, $mid]);
                $after = (int)$m['inventory_id'];
            }
            if ((int)$m['delivered_qty'] >= (int)$m['target_qty']) close_mission($mid, 'completed', 'Delivered & Verified');
            else mission_event($mid, 'verified', number_format($qty) . ' ' . $d['unit'] . ' verified by the Audit Wing');
        } else mission_event((int)$d['mission_id'], 'rejected', $note);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the decision. Please try again.'];
    }
    audit('delivery.' . $decision, 'delivery', $id, $decision === 'verify' ? number_format($d['quantity']) . ' ' . $d['unit'] . ' verified' : ($note ?: 'rejected'));
    if ($d['reported_by']) notify_user((int)$d['reported_by'], 'Delivery ' . ($decision === 'verify' ? 'verified' : 'rejected'), ($d['mcode'] ?: $d['title']) . ($note ? ' — ' . $note : ''), 'confirm.php', 'verification');
    if ($after) check_low_stock($after);
    return [true, $decision === 'verify' ? 'Delivery verified. Mission totals and NGO stock were updated.' : 'Delivery rejected. The NGO team has been told why.'];
}
