<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) audit('auth.logout', 'user', (int)user()['id']);
$_SESSION = [];
session_destroy();
session_start();
flash('info', 'You have been signed out.');
redirect('login.php');
