<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';

session_start();

// ฟังก์ชันช่วยส่ง JSON และออก
function sendJson($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Security checks
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendJson(['success' => false, 'error' => 'สิทธิ์ไม่เพียงพอ']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'error' => 'Method Not Allowed']);
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
}

// Sanitize & validate input
$name        = trim($_POST['name'] ?? '');
$stock       = (int)($_POST['stock'] ?? 0);
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$image       = $_POST['old_image'] ?? '';
$is_update   = !empty($_POST['id']);
$id          = $is_update ? (int)$_POST['id'] : null;

if ($name === '') {
    sendJson(['success' => false, 'error' => 'กรุณากรอกชื่ออุปกรณ์']);
}

// Handle image upload
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allow) || $_FILES['image']['size'] > 5 * 1024 * 1024) {
        sendJson(['success' => false, 'error' => 'รูปไม่ถูกต้อง (jpg/png/gif/webp, ไม่เกิน 5MB)']);
    }

    $image = time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (!is_dir('uploads')) mkdir('uploads', 0755, true);

    if (!move_uploaded_file($_FILES['image']['tmp_name'], "uploads/$image")) {
        sendJson(['success' => false, 'error' => 'อัปโหลดรูปภาพไม่สำเร็จ']);
    }

    // ลบรูปเก่า
    if (!empty($_POST['old_image']) && file_exists("uploads/{$_POST['old_image']}")) {
        @unlink("uploads/{$_POST['old_image']}");
    }
}

$pdo->beginTransaction();

try {
    if ($is_update) {
        // ────────────── แก้ไข ──────────────
        $stmt = $pdo->prepare("SELECT stock FROM items WHERE id = ?");
        $stmt->execute([$id]);
        $old_stock = $stmt->fetchColumn();

        if ($old_stock === false) {
            throw new Exception("ไม่พบอุปกรณ์ ID $id");
        }

        $stmt = $pdo->prepare("
            UPDATE items 
            SET name = ?, category_id = ?, image = ?, stock = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $category_id, $image ?: null, $stock, $id]);

        // บันทึก transaction ถ้า stock เปลี่ยน
        if ($stock != $old_stock) {
            $diff = $stock - $old_stock;
            $type = $diff > 0 ? 'IN' : 'OUT';
            $qty  = abs($diff);

            $stmt = $pdo->prepare("
                INSERT INTO stock_transactions 
                (item_id, type, quantity, stock, transaction_date, memo) 
                VALUES (?, ?, ?, ?, CURDATE(), 'ปรับสต็อกด้วยมือ')
            ");
            $stmt->execute([$id, $type, $qty, $stock]);
        }

        $message = "แก้ไข '$name' เรียบร้อย";
    } else {
        // ────────────── เพิ่มใหม่ ──────────────
        $stmt = $pdo->prepare("
            INSERT INTO items (name, category_id, image, stock) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $category_id, $image ?: null, $stock]);

        $id = $pdo->lastInsertId();

        if ($stock > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_transactions 
                (item_id, type, quantity, stock, transaction_date, memo) 
                VALUES (?, 'IN', ?, ?, CURDATE(), 'รับเข้าครั้งแรก')
            ");
            $stmt->execute([$id, $stock, $stock]);
        }

        $message = "เพิ่ม '$name' เรียบร้อย";
    }

    $pdo->commit();

    // ส่ง JSON สำหรับ AJAX
    sendJson([
        'success'   => true,
        'is_update' => false,          // เพิ่มบรรทัดนี้
        'id'        => $id,
        'item_name' => $name,
        'message'   => $message
    ]);

    // ในกรณีแก้ไข
    sendJson([
        'success'   => true,
        'is_update' => true,           // เพิ่มบรรทัดนี้
        'id'        => $id,
        'item_name' => $name,
        'message'   => $message
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    sendJson([
        'success' => false,
        'error'   => $e->getMessage() ?: 'เกิดข้อผิดพลาดในการบันทึก'
    ]);
}
