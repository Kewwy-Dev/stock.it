<?php
// bootstrap.php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/asset_helper.php';

// สร้าง CSRF token ถ้ายังไม่มี (ถ้าโปรเจ็กต์ใช้)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
