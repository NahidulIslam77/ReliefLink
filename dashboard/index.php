<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$u = require_role(['super_admin', 'district_admin', 'ngo', 'coordinator', 'audit']);
require __DIR__ . '/../includes/overview/' . $u['role'] . '.php';
