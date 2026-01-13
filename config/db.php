<?php
// config/db.php

// โหลด Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// โหลด .env (สำคัญ! ถ้ายังไม่ได้ใส่ ให้เพิ่มตรงนี้)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();           // โหลดค่าจาก .env
// หรือ $dotenv->safeLoad(); ถ้าต้องการไม่ให้ error ถ้า .env หาย

// ดึงค่าจาก environment variables (มี fallback เผื่อกรณีไม่มี .env)
$host    = $_ENV['DB_HOST']    ?? 'localhost';
$db      = $_ENV['DB_NAME']    ?? 'it_stockcard';
$user    = $_ENV['DB_USER']    ?? 'root';
$pass    = $_ENV['DB_PASS']    ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // สำหรับทดสอบ (ลบหรือ comment ออกเมื่อใช้งานจริง)
    // error_log("DB Connected successfully using host: $host, db: $db");
} catch (PDOException $e) {
    // ใน production ควร log แทน die
    error_log('PDO Connection failed: ' . $e->getMessage());
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
}