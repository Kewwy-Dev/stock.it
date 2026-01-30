<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: newuser.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function sendJson($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
        sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    log_event('items', 'ลบอุปกรณ์ไม่สำเร็จ: CSRF token ไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => (int)($_POST['delete_id'] ?? 0)
    ]);
    header('Location: index.php');
    exit;
}

$id = (int)$_POST['delete_id'];

$pdo->beginTransaction();
try {
    // ดึงชื่อไฟล์รูปภาพก่อนลบ
    $stmt = $pdo->prepare("SELECT image, name FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $image = $row['image'] ?? null;
    $item_name = $row['name'] ?? null;

    // ลบรูปภาพ (ถ้ามี)
    if ($image && file_exists('uploads/' . $image)) {
        unlink('uploads/' . $image);
    }

    // ลบรายการอุปกรณ์
    $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);

    $pdo->commit();

    log_event('items', 'ลบอุปกรณ์สำเร็จ', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $id,
        'item_name' => $item_name
    ]);

    if ($isAjax) {
        sendJson(['success' => true, 'id' => $id]);
    }

    $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบอุปกรณ์เรียบร้อย'];
} catch (Exception $e) {
    $pdo->rollBack();
    log_event('items', 'ลบอุปกรณ์ไม่สำเร็จ: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $id,
        'item_name' => $item_name ?? null
    ]);
    if ($isAjax) {
        sendJson(['success' => false, 'error' => 'ลบไม่สำเร็จ: ' . $e->getMessage()]);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ลบไม่สำเร็จ: ' . $e->getMessage()];
}

header('Location: index.php');
exit;
?>
