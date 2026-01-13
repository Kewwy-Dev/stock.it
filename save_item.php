<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';

session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: newuser.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['toast'] = ['type'=>'error','message'=>'Invalid CSRF token'];
    header('Location: index.php'); exit;
}

$name  = trim($_POST['name'] ?? '');
$stock = (int)($_POST['stock'] ?? 0);
$image = $_POST['old_image'] ?? '';

// ตรวจสอบชื่อ
if ($name === '') {
    $_SESSION['toast'] = ['type'=>'error','message'=>'กรุณากรอกชื่ออุปกรณ์'];
    header('Location: index.php'); exit;
}

// อัปโหลดรูปภาพ
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allow) && $_FILES['image']['size'] <= 5*1024*1024) {
        $image = time() . '_' . rand(1000,9999) . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/$image");
        
        // ลบรูปเก่า
        if (!empty($_POST['old_image']) && file_exists("uploads/{$_POST['old_image']}")) {
            unlink("uploads/{$_POST['old_image']}");
        }
    } else {
        $_SESSION['toast'] = ['type'=>'error','message'=>'รูปไม่ถูกต้อง (เฉพาะ jpg/png/gif/webp และไม่เกิน 2MB)'];
        header('Location: index.php'); exit;
    }
}

$pdo->beginTransaction();
try {
    if (!empty($_POST['id'])) {
        // แก้ไข
        $id = (int)$_POST['id'];
        $old = $pdo->prepare("SELECT stock FROM items WHERE id = ?")->execute([$id]);
        $old = $pdo->query("SELECT stock FROM items WHERE id = $id")->fetchColumn();

        $pdo->prepare("UPDATE items SET name = ?, image = ?, stock = ? WHERE id = ?")
            ->execute([$name, $image ?: null, $stock, $id]);

        // ถ้าจำนวนเปลี่ยน → บันทึก transaction
        if ($stock != $old) {
            $diff = $stock - $old;
            $type = $diff > 0 ? 'IN' : 'OUT';
            $qty  = abs($diff);
            $pdo->prepare("INSERT INTO stock_transactions (item_id, type, quantity, stock, transaction_date, memo) 
                           VALUES (?, ?, ?, ?, CURDATE(), 'ปรับสต็อกด้วยมือ')")
                ->execute([$id, $type, $qty, $stock]);
        }

        $msg = "แก้ไข '$name' เรียบร้อย";
    } else {
        // เพิ่มใหม่
        $pdo->prepare("INSERT INTO items (name, image, stock) VALUES (?, ?, ?)")
            ->execute([$name, $image ?: null, $stock]);

        $id = $pdo->lastInsertId();

        if ($stock > 0) {
            $pdo->prepare("INSERT INTO stock_transactions (item_id, type, quantity, stock, transaction_date, memo) 
                           VALUES (?, 'IN', ?, ?, CURDATE(), 'รับเข้าครั้งแรก')")
                ->execute([$id, $stock, $stock]);
        }
        $msg = "เพิ่ม '$name' เรียบร้อย";
    }
    $pdo->commit();
    $_SESSION['toast'] = ['type'=>'success','message'=>$msg];
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['toast'] = ['type'=>'error','message'=>'เกิดข้อผิดพลาด: '.$e->getMessage()];
}

header('Location: index.php');
exit;
?>