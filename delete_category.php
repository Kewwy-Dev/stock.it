<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_event('categories', 'ลบหมวดหมู่ไม่สำเร็จ: คำขอไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    log_event('categories', 'ลบหมวดหมู่ไม่สำเร็จ: ID ไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'category_id' => $id
    ]);
    echo json_encode(['success' => false, 'message' => 'ID ไม่ถูกต้อง']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category_name = $stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    // ถ้าต้องการ set category_id = NULL อัตโนมัติใน items (optional แต่แนะนำ)
    // $pdo->prepare("UPDATE items SET category_id = NULL WHERE category_id = ?")->execute([$id]);

    log_event('categories', 'ลบหมวดหมู่สำเร็จ', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'category_id' => $id,
        'category_name' => $category_name
    ]);
    echo json_encode(['success' => true, 'message' => 'ลบสำเร็จ']);
} catch (PDOException $e) {
    // ถ้า foreign key constrain error → บอกให้ผู้ใช้รู้
    if (stripos($e->getMessage(), 'foreign key')) {
        log_event('categories', 'ลบหมวดหมู่ไม่สำเร็จ: มีการใช้งานอยู่', [
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'category_id' => $id,
            'category_name' => $category_name
        ]);
        echo json_encode([
            'success' => false,
            'message' => 'ไม่สามารถลบได้ เพราะยังมีอุปกรณ์อยู่ในหมวดหมู่นี้'
        ]);
    } else {
        log_event('categories', 'ลบหมวดหมู่ไม่สำเร็จ: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'category_id' => $id,
            'category_name' => $category_name
        ]);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}
exit;
