<?php
/* Templates: base head, public site, auth side panel, dashboard shell. */

function logo(): string {
    return '<span class="logo"><svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#2F6BFF"/><path d="M8 12l8-4 8 4-8 4-8-4zm0 5l8 4 8-4M8 21l8 4 8-4" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg><b>Relief<i>Link</i></b></span>';
}

function head(string $title, string $bodyClass = ''): void {
    $u = user(); $st = $u ? user_settings($u['settings'] ?? null) : [];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=1280">'
       . '<title>' . e($title) . ' · ' . e(APP_NAME) . '</title>'
       . '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Crect width=%2732%27 height=%2732%27 rx=%278%27 fill=%27%232F6BFF%27/%3E%3C/svg%3E">'
       . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
       . '<body class="' . e($bodyClass) . ($st && !empty($st['datasaver']) ? ' data-saver' : '') . '" data-base="' . e(base_path()) . '" data-csrf="' . e(csrf_token()) . '"'
       . ($u ? ' data-offline="' . (int)$st['offline'] . '" data-autosync="' . (int)$st['autosync'] . '" data-datasaver="' . (int)$st['datasaver'] . '" data-uid="' . (int)$u['id'] . '"' : '') . '>';
    if (!empty($_SESSION['flash'])) {
        echo '<div class="flash-wrap" role="status">';
        foreach ($_SESSION['flash'] as [$t, $m]) echo '<div class="flash ' . e($t) . '"><span>' . e($m) . '</span><button type="button" aria-label="Dismiss" data-dismiss>×</button></div>';
        echo '</div>';
        $_SESSION['flash'] = [];
    }
}

function public_head(string $title, string $active = ''): void {
    head($title, 'public');
    $u = user();
    $links = ['home' => ['', 'Home'], 'works' => ['how-it-works.php', 'How it works'], 'disasters' => ['disasters.php', 'Active disasters'], 'donate' => ['donate.php', 'Donate']];
    echo '<header class="nav"><div class="container nav-in"><a href="' . e(url('')) . '" class="brand" aria-label="ReliefLink home">' . logo() . '</a><nav aria-label="Main">';
    foreach ($links as $k => [$href, $label]) echo '<a href="' . e(url($href)) . '"' . ($active === $k ? ' class="on"' : '') . '>' . e($label) . '</a>';
    echo '</nav><div class="nav-cta"><a class="btn btn-red btn-sm" href="' . e(url('sos.php')) . '">Emergency SOS</a>';
    echo $u ? '<a class="btn btn-primary btn-sm" href="' . e(url('dashboard/')) . '">Dashboard</a>' : '<a class="btn btn-outline btn-sm" href="' . e(url('login.php')) . '">Sign in</a>';
    echo '</div></div></header>';
}

function public_foot(): void {
    echo '<footer class="foot"><div class="container foot-in"><div><span class="logo-w">' . logo() . '</span><p>Bangladesh&rsquo;s national platform for coordinating disaster relief between government, NGOs and field coordinators.</p></div>'
       . '<div><h5>Platform</h5><a href="' . e(url('how-it-works.php')) . '">How it works</a><a href="' . e(url('disasters.php')) . '">Active disasters</a><a href="' . e(url('donate.php')) . '">Donate</a><a href="' . e(url('login.php')) . '">Sign in</a></div>'
       . '<div><h5>Emergency</h5><p>In immediate danger call <b>999</b>.<br>National EOC: <b>' . e(EOC_PHONE) . '</b></p><a href="' . e(url('sos.php')) . '">Send an SOS</a></div></div>'
       . '<div class="container foot-bot">© ' . date('Y') . ' ReliefLink · Smart Disaster Response &amp; Relief Coordination</div></footer>'
       . '<script src="' . e(url('assets/js/map.js')) . '"></script><script src="' . e(url('assets/js/charts.js')) . '"></script><script src="' . e(url('assets/js/main.js')) . '"></script></body></html>';
}

function auth_side(string $title, string $text, bool $compact = false): void {
    $img = is_file(__DIR__ . '/../assets/img/relief.jpg') ? ' style="background-image:linear-gradient(180deg,rgba(8,16,40,.15),rgba(8,16,40,.85)),url(' . e(url('assets/img/relief.jpg')) . ')"' : '';
    echo '<aside class="auth-side"' . $img . '><a href="' . e(url('')) . '" class="brand">' . logo() . '</a>'
       . '<div class="auth-copy"><h2>' . $title . '</h2><p>' . e($text) . '</p>'
       . '<ul><li>🔐 Role-based secure access</li><li>🇧🇩 64 districts · 8 divisions</li><li>🕐 24/7 round-the-clock operations</li></ul></div></aside>';
}

/* ---------------- icons ---------------- */
function icon(string $n): string {
    static $p = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'map' => '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'box' => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'truck' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'check' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'sync' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'dollar' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'wifi' => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$n] ?? $p['grid']) . '</svg>';
}

/** KPI card. $icon is an icon() name; $ic one of b,r,g,a,p,o. */
function kpi(string $tone, string $label, $value, string $sub, string $icon, string $ic = 'b'): string {
    return '<div class="kpi ' . e($tone) . '"><div><small>' . e($label) . '</small><b>' . (is_int($value) ? number_format($value) : e((string)$value)) . '</b><span>' . e($sub) . '</span></div><span class="ic ' . e($ic) . '">' . icon($icon) . '</span></div>';
}

/* ---------------- dashboard shell ---------------- */
function dash_nav(string $role): array {
    // [file key, label, icon]
    $common = [['notifications', 'Notifications', 'bell'], ['profile', 'My Profile', 'user'], ['settings', 'Settings', 'sliders'], ['help', 'Help & Emergency Info', 'help']];
    $own = match ($role) {
        'super_admin' => [['index', 'Dashboard', 'home'], ['disasters', 'Disaster Management', 'alert'], ['zones', 'Disaster Zones', 'pin'], ['districts', 'District Management', 'grid'], ['ngos', 'NGO / Volunteer Mgmt', 'users'], ['users', 'User & Role Management', 'user'],
            ['demands', 'Demand Monitoring', 'clipboard'], ['missions', 'Relief Monitoring', 'zap'], ['tracking', 'Live Tracking', 'truck'], ['deliveries', 'Deliveries', 'check'], ['sos', 'SOS Queue', 'alert'], ['inventory', 'Inventory', 'box'], ['donations', 'Donations', 'dollar'], ['audit', 'Audit log', 'shield']],
        'district_admin' => [['index', 'Dashboard', 'home'], ['disasters', 'Disasters', 'alert'], ['zones', 'Disaster Zones', 'pin'], ['demands', 'Demand Management', 'clipboard'], ['ngos', 'NGO Coordination', 'users'], ['missions', 'Relief Dispatch', 'zap'], ['tracking', 'Live Tracking', 'truck'], ['sos', 'SOS Monitoring', 'alert'], ['analytics', 'District Analytics', 'chart']],
        'ngo' => [['index', 'Dashboard', 'home'], ['inventory', 'Inventory Management', 'box'], ['available', 'Available Demands', 'clipboard'], ['missions', 'My Missions', 'zap'], ['dispatch', 'Dispatch Relief', 'alert'], ['tracking', 'Live Tracking', 'pin'], ['team', 'Team Management', 'users'], ['vehicles', 'Vehicle Management', 'truck'], ['confirm', 'Delivery Confirmation', 'check']],
        'coordinator' => [['index', 'Dashboard', 'home'], ['demand-new', 'Submit Demand', 'plus'], ['demands', 'Demand History', 'clipboard'], ['missions', 'Active Missions', 'zap'], ['sos', 'Emergency SOS', 'alert'], ['confirm', 'Delivery Confirmation', 'check'], ['sync', 'Offline & Sync', 'sync']],
        default => [['index', 'Audit Dashboard', 'home'], ['deliveries', 'Pending Verification', 'clipboard'], ['history', 'Audit History', 'clock'], ['report', 'Audit Report', 'file'], ['donations', 'Donations', 'dollar'], ['audit', 'Audit log', 'shield']],
    };
    return array_merge($own, $common);
}

/** District restriction for scoped roles; returns [sql fragment, params] on column $col. */
function district_scope(array $u, string $col): array {
    return in_array($u['role'], ['district_admin', 'coordinator'], true) && $u['district_id'] ? [" AND $col = ?", [(int)$u['district_id']]] : ['', []];
}

/** Missions visible to the user (alias m = relief_missions, d = disasters). */
function mission_scope(array $u): array {
    return match ($u['role']) {
        'ngo'            => [' AND m.ngo_id = ?', [(int)$u['id']]],
        'coordinator'    => [' AND (m.coordinator_id = ? OR m.zone_id = ?)', [(int)$u['id'], (int)($u['zone_id'] ?? 0)]],
        'district_admin' => [' AND d.district_id = ?', [(int)$u['district_id']]],
        default          => ['', []],
    };
}

function dash_counts(array $u): array {
    $c = []; $r = $u['role'];
    [$dsd, $dpd] = district_scope($u, 'district_id');
    if (in_array($r, ['super_admin', 'district_admin'], true)) $c['demands'] = [(int)val("SELECT COUNT(*) FROM demands WHERE status='pending'$dsd", $dpd), 'amber'];
    if (in_array($r, ['super_admin', 'district_admin'], true)) $c['sos'] = (int)val("SELECT COUNT(*) FROM sos_requests WHERE status='new'$dsd", $dpd);
    if (in_array($r, ['super_admin', 'audit'], true)) { $c['deliveries'] = (int)val("SELECT COUNT(*) FROM deliveries WHERE status='pending'"); $c['donations'] = [(int)val("SELECT COUNT(*) FROM donations WHERE status='pending'"), 'amber']; }
    if ($r === 'super_admin') $c['users'] = [(int)val("SELECT COUNT(*) FROM users WHERE status='pending'"), 'amber'];
    if ($r === 'ngo') {
        $c['available'] = [(int)val("SELECT COUNT(*) FROM demands d WHERE d.status='pending' AND NOT EXISTS (SELECT 1 FROM zone_locks l WHERE l.zone_id=d.zone_id AND l.item_type=d.item_type AND l.active_key=1)"), 'amber'];
        $c['missions'] = [(int)val("SELECT COUNT(*) FROM relief_missions WHERE ngo_id=? AND status='assigned'", [(int)$u['id']]), 'blue'];
        $c['confirm'] = [(int)val("SELECT COUNT(*) FROM relief_missions WHERE ngo_id=? AND status IN ('in_transit','arrived')", [(int)$u['id']]), 'blue'];
    }
    if ($r === 'coordinator') {
        [$ms, $mp] = mission_scope($u);
        $c['missions'] = [(int)val("SELECT COUNT(*) FROM relief_missions m JOIN disasters d ON d.id=m.disaster_id WHERE m.status IN ('accepted','in_transit','arrived')$ms", $mp), 'blue'];
    }
    $c['notifications'] = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [(int)$u['id']]);
    return $c;
}

function dash_head(string $title, string $active, string $sub = ''): void {
    $u = user();
    head($title, 'dash-page role-' . ($u['role'] ?? ''));
    if (!$u) return;
    $counts = dash_counts($u);
    $ini = mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1);
    echo '<div class="dash"><aside class="side"><div class="side-head"><span class="mark">' . icon('layers') . '</span><div><b>ReliefLink</b><small>' . e(ROLES[$u['role']][0]) . '</small></div></div>'
       . '<div class="who"><span class="av">' . e($ini) . '</span><div><b>' . e($u['first_name'] . ' ' . $u['last_name']) . '</b><small>' . e($u['organization'] ?: ($u['district_name'] ?: 'National')) . '</small></div></div><nav aria-label="Dashboard">';
    foreach (dash_nav($u['role']) as [$k, $label, $ic]) {
        $n = $counts[$k] ?? 0; $tone = 'red'; if (is_array($n)) { [$n, $tone] = $n; }
        echo '<a href="' . e(url('dashboard/' . ($k === 'index' ? '' : $k . '.php'))) . '"' . ($active === $k ? ' class="on" aria-current="page"' : '') . '><span class="ni">' . icon($ic) . '</span>' . e($label)
           . ($n ? '<em class="' . $tone . '">' . $n . '</em>' : '') . ($k === 'sync' ? '<em class="amber" data-sync-badge hidden></em>' : '') . '</a>';
    }
    echo '</nav><div class="side-foot"><a href="' . e(url('')) . '">' . icon('external') . 'Public Site</a><a href="' . e(url('logout.php')) . '">' . icon('logout') . 'Sign Out</a></div></aside>'
       . '<div class="main"><header class="top"><div><h1>' . e($title) . '</h1>' . ($sub ? '<p>' . e($sub) . '</p>' : '') . '</div>'
       . '<div class="top-r"><span class="net-pill" data-net><i></i><span>Live</span></span><span class="date-pill">' . date('M j, Y') . '</span><a class="bell" href="' . e(url('dashboard/notifications.php')) . '" title="Notifications" aria-label="Notifications">' . icon('bell') . (($counts['notifications'] ?? 0) ? '<em></em>' : '') . '</a><span class="av sm">' . e($ini) . '</span></div></header><main class="content">';
}

function dash_foot(): void {
    echo '</main></div></div><script src="' . e(url('assets/js/map.js')) . '"></script><script src="' . e(url('assets/js/charts.js')) . '"></script><script src="' . e(url('assets/js/main.js')) . '"></script></body></html>';
}

/** Filter tabs: $tabs = [key => label]; renders links that set ?{$param}=key. */
function tabs(array $tabs, string $cur, string $param = 'status', array $counts = []): void {
    echo '<div class="toolbar" role="tablist">';
    foreach ($tabs as $k => $label) {
        $q = array_merge($_GET, [$param => $k]); unset($q['id'], $q['match'], $q['view'], $q['accept']);
        echo '<a class="btn btn-sm ' . ($cur === (string)$k ? 'btn-primary' : 'btn-outline') . '" href="?' . e(http_build_query($q)) . '">' . e($label) . (isset($counts[$k]) ? ' (' . (int)$counts[$k] . ')' : '') . '</a>';
    }
    echo '</div>';
}

function pct($done, $total): int { return $total > 0 ? (int)min(100, round($done / $total * 100)) : 0; }
function empty_row(int $cols, string $msg): string { return '<tr><td colspan="' . $cols . '" class="empty">' . e($msg) . '</td></tr>'; }
function prio_badge(string $p): string { return badge($p); }
