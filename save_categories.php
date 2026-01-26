<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อหมวดหมู่']);
    exit;
}

try {
    if ($id > 0) {
        // update
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขสำเร็จ']);
    } else {
        // insert
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        $newId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'เพิ่มสำเร็จ',
            'new_id' => $newId
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
exit;