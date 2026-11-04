<?php
/* Receives records saved offline on a field device (JSON) and creates them idempotently via client_uid. */
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function out(array $a, int $code = 200): never { http_response_code($code); echo json_encode($a); exit; }
if (!is_post()) out(['ok' => false, 'error' => 'POST required'], 405);
$u = user(); if (!$u) out(['ok' => false, 'error' => 'Please sign in again to sync.'], 401);
if (!hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) out(['ok' => false, 'error' => 'Session expired. Reload the page and sync again.'], 419);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !isset($in['type'], $in['data']) || !is_array($in['data'])) out(['ok' => false, 'error' => 'Bad request'], 400);
$d = $in['data']; $d['client_uid'] = substr((string)($in['uid'] ?? ''), 0, 40);
if ($d['client_uid'] === '') out(['ok' => false, 'error' => 'Missing record id'], 400);
try {
    [$ok, $msg] = match ($in['type']) {
        'demand'  => $u['role'] === 'coordinator' ? array_slice(create_demand($u, $d), 0, 2) : [false, 'Only Spot Coordinators can submit demands.'],
        'sos'     => $u['role'] === 'coordinator' ? array_slice(create_sos_alert($u, $d), 0, 2) : [false, 'Only Spot Coordinators can send SOS alerts.'],
        'confirm' => $u['role'] === 'coordinator' ? coordinator_confirm($u, $d) : [false, 'Only Spot Coordinators can confirm receipt.'],
        default   => [false, 'Unknown record type'],
    };
} catch (Throwable $e) { out(['ok' => false, 'error' => 'Server error. It will stay queued.'], 500); }
out($ok ? ['ok' => true, 'message' => $msg] : ['ok' => false, 'error' => $msg]);
