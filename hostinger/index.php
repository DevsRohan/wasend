<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (auth_user()) {
    header('Location: dashboard.php');
    exit;
}
header('Location: login.php');
exit;
