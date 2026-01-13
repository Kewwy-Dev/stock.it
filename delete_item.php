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

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    header('Location: index.php');
    exit;
}

$id = (int)$_POST['delete_id'];

$pdo->beginTransaction();
try {
    // ดึงชื่อไฟล์รูปภาพก่อนลบ
    $stmt = $pdo->prepare("SELECT image FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetchColumn();  // ถูกต้อง: ใช้จาก $stmt

    // ลบรูปภาพ (ถ้ามี)
    if ($image && file_exists('uploads/' . $image)) {
        unlink('uploads/' . $image);
    }

    // ลบรายการอุปกรณ์
    $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);

    $pdo->commit();
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบอุปกรณ์เรียบร้อย'];
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ลบไม่สำเร็จ: ' . $e->getMessage()];
}

header('Location: index.php');
exit;
?>