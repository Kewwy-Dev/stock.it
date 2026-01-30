<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_event('categories', 'บันทึกหมวดหมู่ไม่สำเร็จ: คำขอไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if (empty($name)) {
    log_event('categories', 'บันทึกหมวดหมู่ไม่สำเร็จ: ไม่ระบุชื่อหมวดหมู่', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อหมวดหมู่']);
    exit;
}

// ตรวจสอบชื่อหมวดหมู่ซ้ำ (ไม่รวมตัวเองตอนแก้ไข)
$stmtDup = $pdo->prepare("
    SELECT id FROM categories
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id <> ?
    LIMIT 1
");
$stmtDup->execute([$name, $id]);
if ($stmtDup->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'ชื่อหมวดหมู่นี้มีอยู่แล้ว']);
    exit;
}

try {
    if ($id > 0) {
        // update
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        log_event('categories', 'แก้ไขหมวดหมู่สำเร็จ', [
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'category_id' => $id,
            'name' => $name
        ]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขสำเร็จ']);
    } else {
        // insert
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        $newId = $pdo->lastInsertId();
        log_event('categories', 'เพิ่มหมวดหมู่สำเร็จ', [
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'category_id' => $newId,
            'name' => $name
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'เพิ่มสำเร็จ',
            'new_id' => $newId
        ]);
    }
} catch (PDOException $e) {
    log_event('categories', 'บันทึกหมวดหมู่ไม่สำเร็จ: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'category_id' => $id,
        'name' => $name
    ]);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
exit;
