<?php
// update_removebg_key.php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (empty($csrf_token) || $csrf_token !== $session_token) {
    echo json_encode([
        'success' => false,
        'error'   => 'CSRF token ไม่ถูกต้อง (ส่ง: ' . substr($csrf_token, 0, 10) . '... | คาดหวัง: ' . substr($session_token, 0, 10) . '...)'
    ]);
    exit;
}

$new_key = trim($_POST['new_key'] ?? '');
if (empty($new_key)) {
    echo json_encode(['success' => false, 'error' => 'API Key ห้ามว่าง']);
    exit;
}
if (strlen($new_key) < 20) { // remove.bg key ปกติยาว ~22 ตัว
    echo json_encode(['success' => false, 'error' => 'API Key ดูเหมือนไม่ถูกต้อง (สั้นเกินไป)']);
    exit;
}

// บันทึกใน session
$_SESSION['removebg_api_key'] = $new_key;

echo json_encode(['success' => true]);