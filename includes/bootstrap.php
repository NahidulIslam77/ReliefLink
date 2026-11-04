<?php
declare(strict_types=1);
require __DIR__ . '/../config/config.php';

date_default_timezone_set('Asia/Dhaka');

const DISASTER_TYPES = ['flood', 'cyclone', 'landslide', 'erosion', 'earthquake', 'fire', 'drought', 'other'];
const SEVERITIES     = ['low', 'medium', 'high', 'critical'];
const ITEM_TYPES     = ['food', 'water', 'medicine', 'shelter', 'hygiene', 'clothing', 'rescue', 'other'];
const SIGNUP_ROLES   = ['district_admin', 'ngo', 'coordinator', 'audit'];
// role key => [label, description, icon]
const ROLES = [
    'super_admin'    => ['Super Admin', 'National oversight access', '🏛️'],
    'district_admin' => ['District Admin', 'District-level coordination', '🏢'],
    'ngo'            => ['NGO / Volunteer Team', 'Field operations & delivery', '🤝'],
    'coordinator'    => ['Spot Coordinator', 'On-ground reporting', '📍'],
    'audit'          => ['Audit Wing', 'Verification & oversight', '✅'],
];

/* Fallbacks so the app also runs on hosts without the mbstring extension. */
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null): string {
        preg_match_all('/./su', $s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return (int)preg_match_all('/./su', $s); }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

/* ---------- paths & output ---------- */
function base_path(): string {
    static $b = null;
    if ($b !== null) return $b;
    $root = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $doc  = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc !== '' && strpos($root, $doc) === 0) return $b = rtrim(substr($root, strlen($doc)), '/');
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = preg_replace('#/(dashboard|api)$#', '', $dir);
    return $b = rtrim($dir === '/' ? '' : $dir, '/');
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never {
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $default = ''): string {
    $v = $_POST[$k] ?? $default;
    return is_array($v) ? $default : trim((string)$v);
}
function old(string $k): string { return e($_POST[$k] ?? ''); }
function opts(array $items, ?string $sel = null): string {
    $o = '';
    foreach ($items as $i) $o .= '<option value="' . e($i) . '"' . ((string)$sel === (string)$i ? ' selected' : '') . '>' . e(ucfirst(str_replace('_', ' ', (string)$i))) . '</option>';
    return $o;
}
function money($n): string { return '৳' . number_format((float)$n); }
function ago(?string $dt): string {
    if (!$dt) return '';
    $s = max(0, time() - strtotime($dt));
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 30) return floor($s / 86400) . ' d ago';
    return date('j M Y', strtotime($dt));
}
function badge(string $status): string {
    static $map = [
        'critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'green',
        'active' => 'red', 'monitoring' => 'amber', 'resolved' => 'green',
        'pending' => 'amber', 'confirmed' => 'green', 'verified' => 'green', 'rejected' => 'red',
        'new' => 'red', 'assigned' => 'blue', 'planned' => 'gray', 'in_transit' => 'blue',
        'completed' => 'green', 'cancelled' => 'gray', 'suspended' => 'red',
    ];
    return '<span class="badge b-' . ($map[$status] ?? 'gray') . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/* ---------- database ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+06:00'");
    } catch (PDOException $ex) {
        http_response_code(500);
        exit('<h1>Database connection failed</h1><p>Import <code>database/schema.sql</code> and check <code>config/config.php</code>.</p>');
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement {
    $st = db()->prepare($sql);
    foreach (array_values($p) as $i => $v) {
        $st->bindValue($i + 1, $v, $v === null ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    $st->execute();
    return $st;
}
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

/* ---------- CSRF, flash ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = (string)($_POST['_csrf'] ?? '');
    if ($t === '' || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

/* ---------- auth ---------- */
function user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT u.*, d.name AS district_name FROM users u LEFT JOIN districts d ON d.id=u.district_id WHERE u.id=?', [(int)$_SESSION['uid']]);
        if (!$u || $u['status'] !== 'active') { unset($_SESSION['uid']); $u = null; }
    }
    return $u;
}
function require_role(array $roles): array {
    $u = user();
    if (!$u) { flash('info', 'Please sign in to continue.'); redirect('login.php'); }
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        dash_head('Not allowed', '');
        echo '<div class="panel"><div class="empty">Your role (' . e(ROLES[$u['role']][0]) . ') cannot open this page.</div></div>';
        dash_foot();
        exit;
    }
    return $u;
}
function audit(string $action, string $entity = '', ?int $id = null, string $detail = ''): void {
    q('INSERT INTO audit_logs (user_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)',
      [user()['id'] ?? null, $action, $entity, $id, mb_substr($detail, 0, 250), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/* ---------- rate limiting ---------- */
function throttle(string $key, int $max = 5, int $window = 300): bool {
    if (random_int(1, 50) === 1) q('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    return (int)val('SELECT COUNT(*) FROM rate_limits WHERE k=? AND ip=? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
        [$key, $_SERVER['REMOTE_ADDR'] ?? '', $window]) < $max;
}
function throttle_hit(string $key): void { q('INSERT INTO rate_limits (k, ip) VALUES (?,?)', [$key, $_SERVER['REMOTE_ADDR'] ?? '']); }

/* ---------- notifications ---------- */
const NOTIFY_SETTING = ['sos' => 'alert_sos', 'mission' => 'alert_mission', 'verification' => 'alert_mission', 'inventory' => 'alert_mission',
                        'demand' => 'alert_demand', 'disaster' => 'alert_system', 'account' => 'alert_system', 'system' => 'alert_system'];
function user_settings(?string $json): array {
    $d = ['alert_sos' => 1, 'alert_mission' => 1, 'alert_demand' => 1, 'alert_system' => 1, 'email' => 1, 'sms' => 1, 'offline' => 1, 'autosync' => 1, 'datasaver' => 0];
    $j = $json ? json_decode($json, true) : null;
    return is_array($j) ? array_merge($d, array_map('intval', $j)) : $d;
}
function notify_user(int $uid, string $title, string $body = '', string $link = '', string $category = 'system'): void {
    $s = user_settings(val('SELECT settings FROM users WHERE id=?', [$uid]));
    if (!($s[NOTIFY_SETTING[$category] ?? 'alert_system'] ?? 1)) return;
    q('INSERT INTO notifications (user_id,title,body,link,category) VALUES (?,?,?,?,?)', [$uid, $title, mb_substr($body, 0, 290), $link, $category]);
}
function notify_where(string $where, array $p, string $title, string $body = '', string $link = '', string $category = 'system'): void {
    foreach (rows("SELECT id FROM users WHERE status='active' AND ($where)", $p) as $r) notify_user((int)$r['id'], $title, $body, $link, $category);
}

/* ---------- data helpers ---------- */
function district_options(int $sel = 0): string {
    $o = '<option value="">Select district</option>'; $g = '';
    foreach (rows('SELECT id,name,division FROM districts ORDER BY division,name') as $d) {
        if ($g !== $d['division']) { if ($g !== '') $o .= '</optgroup>'; $g = $d['division']; $o .= '<optgroup label="' . e($g) . '">'; }
        $o .= '<option value="' . (int)$d['id'] . '"' . ($sel === (int)$d['id'] ? ' selected' : '') . '>' . e($d['name']) . '</option>';
    }
    return $o . ($g !== '' ? '</optgroup>' : '');
}

require __DIR__ . '/domain.php';
