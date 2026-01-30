<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

if (empty($_SESSION['user_id'])) {
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: ยังไม่เข้าสู่ระบบ', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_role'] !== 'admin') {
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: สิทธิ์ไม่เพียงพอ', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    header('Location: newuser.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: Method Not Allowed', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    header('Location: index.php'); exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['toast'] = ['type'=>'error','message'=>'Invalid CSRF token'];
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: CSRF token ไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest'
    ]);
    header('Location: index.php'); exit;
}

$item_id   = (int)$_POST['item_id'];
$type      = strtoupper($_POST['type']); // IN หรือ OUT
$quantity  = (int)$_POST['quantity'];
$date      = $_POST['transaction_date'] ?? date('Y-m-d');
$memo      = trim($_POST['memo'] ?? '');
$company_id = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
$employee_id = null;
$department_id = null;
$item_name = null;

$stmtItem = $pdo->prepare("SELECT name FROM items WHERE id = ? LIMIT 1");
$stmtItem->execute([$item_id]);
$item_name = $stmtItem->fetchColumn() ?: null;

if ($type === 'OUT') {
    $employee_id   = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    // ตรวจสอบว่าผู้เบิกตรงกับแผนกหรือไม่ (ถ้ามี)
    if ($employee_id) {
        $emp = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?")
                   ->execute([$employee_id]);
        $emp = $pdo->query("SELECT department_id FROM employees WHERE id = $employee_id")->fetchColumn();
        if ($emp != $department_id) {
            $_SESSION['toast'] = ['type'=>'error','message'=>'ผู้เบิกไม่ตรงกับแผนก'];
            log_event('transactions', 'บันทึกรายการไม่สำเร็จ: ผู้เบิกไม่ตรงกับแผนก', [
                'user_id' => $_SESSION['user_id'] ?? 'guest',
                'employee_id' => $employee_id,
                'department_id' => $department_id
            ]);
            header('Location: index.php'); exit;
        }
    }
}

if ($quantity < 1 || !in_array($type, ['IN','OUT'])) {
    $_SESSION['toast'] = ['type'=>'error','message'=>'ข้อมูลไม่ถูกต้อง'];
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: ข้อมูลไม่ถูกต้อง', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $item_id,
        'item_name' => $item_name,
        'quantity' => $quantity
    ]);
    header('Location: index.php'); exit;
}

// ดึงสต็อกปัจจุบัน
$current = $pdo->prepare("SELECT stock FROM items WHERE id = ?")->execute([$item_id]);
$current_stock = $pdo->query("SELECT stock FROM items WHERE id = $item_id")->fetchColumn();

if ($current_stock === false) {
    $_SESSION['toast'] = ['type'=>'error','message'=>'ไม่พบอุปกรณ์'];
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: ไม่พบอุปกรณ์', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $item_id,
        'item_name' => $item_name
    ]);
    header('Location: index.php'); exit;
}

$new_stock = $type === 'IN' ? $current_stock + $quantity : $current_stock - $quantity;
if ($new_stock < 0) {
    $_SESSION['toast'] = ['type'=>'error','message'=>'สต็อกไม่เพียงพอ'];
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: สต็อกไม่เพียงพอ', [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $item_id,
        'item_name' => $item_name,
        'quantity' => $quantity,
        'current_stock' => $current_stock
    ]);
    header('Location: index.php'); exit;
}

$pdo->beginTransaction();
try {
    // บันทึก transaction
    $pdo->prepare("
        INSERT INTO stock_transactions 
        (item_id, employee_id, company_id, type, quantity, stock, transaction_date, memo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$item_id, $employee_id, $company_id, $type, $quantity, $new_stock, $date, $memo]);

    // อัปเดตสต็อก
    $pdo->prepare("UPDATE items SET stock = ? WHERE id = ?")
        ->execute([$new_stock, $item_id]);

    $pdo->commit();
    $_SESSION['toast'] = ['type'=>'success','message'=> 
        $type === 'IN' ? "เพิ่มสต็อก +$quantity ชิ้น" : "เบิก -$quantity ชิ้น"
    ];
    $action_text = $type === 'IN' ? 'เพิ่มสำเร็จ' : 'เบิกสำเร็จ';
    log_event('transactions', $action_text, [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $item_id,
        'item_name' => $item_name,
        'quantity' => $quantity,
        'stock_after' => $new_stock
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['toast'] = ['type'=>'error','message'=>'เกิดข้อผิดพลาด: '.$e->getMessage()];
    log_event('transactions', 'บันทึกรายการไม่สำเร็จ: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'item_id' => $item_id,
        'item_name' => $item_name,
        'quantity' => $quantity
    ]);
}

header('Location: index.php');
exit;
?>
