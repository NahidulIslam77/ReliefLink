<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'active_disasters' => (int)val('SELECT COUNT(*) FROM disasters WHERE status="active"'),
    'districts'        => (int)val('SELECT COUNT(DISTINCT district_id) FROM disasters WHERE status="active"'),
    'missions'         => (int)val('SELECT COUNT(*) FROM relief_missions'),
    'people_reached'   => (int)val('SELECT COALESCE(SUM(recipients),0) FROM deliveries WHERE status="verified"'),
    'donated'          => (float)val('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status="confirmed"'),
]);
