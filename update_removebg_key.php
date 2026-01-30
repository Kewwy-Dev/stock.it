<?php
// update_removebg_key.php
session_start();
require_once __DIR__ . '/includes/logger.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_event('system', 'อัปเดต remove.bg key ไม่สำเร็จ: Method Not Allowed', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (empty($csrf_token) || $csrf_token !== $session_token) {
    log_event('system', 'อัปเดต remove.bg key ไม่สำเร็จ: CSRF token ไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode([
        'success' => false,
        'error'   => 'CSRF token ไม่ถูกต้อง (ส่ง: ' . substr($csrf_token, 0, 10) . '... | คาดหวัง: ' . substr($session_token, 0, 10) . '...)'
    ]);
    exit;
}

$new_key = trim($_POST['new_key'] ?? '');
if (empty($new_key)) {
    log_event('system', 'อัปเดต remove.bg key ไม่สำเร็จ: ไม่ระบุ API Key', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode(['success' => false, 'error' => 'API Key ห้ามว่าง']);
    exit;
}
if (strlen($new_key) < 20) { // remove.bg key ปกติยาว ~22 ตัว
    log_event('system', 'อัปเดต remove.bg key ไม่สำเร็จ: API Key สั้นเกินไป', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'key_length' => strlen($new_key)
    ]);
    echo json_encode(['success' => false, 'error' => 'API Key ดูเหมือนไม่ถูกต้อง (สั้นเกินไป)']);
    exit;
}

// บันทึกใน session
$_SESSION['removebg_api_key'] = $new_key;
log_event('system', 'อัปเดต remove.bg key สำเร็จ', [
    'user_id' => $_SESSION['user_id'] ?? 'guest',
    'key_length' => strlen($new_key)
]);

echo json_encode(['success' => true]);
