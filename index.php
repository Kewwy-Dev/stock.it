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

$items = $pdo->query("SELECT id, name, image, stock, is_favorite FROM items ORDER BY is_favorite DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$json_items = json_encode($items, JSON_UNESCAPED_UNICODE);

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$companies   = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$employees   = $pdo->query("SELECT e.id, e.name, e.department_id, d.name AS dept_name 
                           FROM employees e LEFT JOIN departments d ON e.department_id = d.id 
                           ORDER BY d.name, e.name")->fetchAll();
$json_employees = json_encode($employees, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="th" <?php if (isset($_SESSION['toast'])): ?>data-toast='<?= json_encode($_SESSION['toast']) ?>' <?php unset($_SESSION['toast']);
                                                                                                          endif; ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock-IT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>

  </style>
</head>

<body class="bg-light">
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">รายการอุปกรณ์ในแผนก IT</h2>
      <select id="itemFilter" class="form-select w-auto">
        <option value="">— ทุกอุปกรณ์ —</option>
        <?php foreach ($items as $i): ?>
          <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></option>
        <?php endforeach; ?>
        <option class="bg-warning-subtle" value="low">อุปกรณ์เหลือน้อย</option>
        <option class="bg-danger-subtle" value="zero">อุปกรณ์หมด</option>
      </select>
    </div>

    <div class="row g-4" id="itemGrid"></div>
    <div id="noResults" class="text-center py-5 text-muted d-none">
      <i class="bi bi-inbox display-1"></i>
      <p>ไม่พบอุปกรณ์</p>
    </div>
  </div>
  <div class="additem_button">
    <div class="showtext-additem">เพิ่มอุปกรณ์</div>
    <button class="fab" onclick="openAddModal()">+</button>
  </div>

  <div class="toast-container position-fixed top-0 end-0 p-3"></div>

  <!-- Modal เพิ่ม/แก้ไขอุปกรณ์ -->
  <div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">เพิ่มอุปกรณ์</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="save_item.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="itemId" name="id">
            <input type="hidden" id="oldImage" name="old_image">
            <div class="mb-3">
              <label class="form-label">ชื่ออุปกรณ์ <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">รูปภาพ (≤5MB)</label>
              <input type="file" class="form-control" name="image" accept="image/*">
              <div id="imagePreview" class="mt-2 text-center"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">จำนวนเริ่มต้น</label>
              <input type="number" class="form-control" name="stock" min="0" value="0">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>บันทึก</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal เพิ่ม/เบิก -->
  <div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="transTitle">เพิ่มสต็อก</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="save_transaction.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="item_id" name="item_id">
            <input type="hidden" id="type" name="type">
            <div class="mb-3">
              <label class="form-label text-danger">จำนวน *</label>
              <input type="number" class="form-control" name="quantity" min="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label">วันที่</label>
              <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">หมายเหตุ</label>
              <textarea class="form-control" name="memo" rows="2"></textarea>
            </div>
            <div id="outSection" style="display:none;">
              <div class="mb-3">
                <label class="form-label">บริษัท</label>
                <select class="form-select" name="company_id">
                  <option value="">— ไม่ระบุ —</option>
                  <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">แผนก</label>
                <select id="departmentSelect" class="form-select">
                  <option value="">— เลือกแผนก —</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" id="department_id" name="department_id">
              </div>
              <div class="mb-3">
                <label class="form-label">ผู้เบิก</label>
                <select id="employeeSelect" class="form-select" name="employee_id" disabled>
                  <option value="">— เลือกผู้เบิก —</option>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>บันทึก</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal ลบ -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5>ยืนยันการลบ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">ต้องการลบ <span id="deleteName"></span> หรือไม่?</div>
        <div class="modal-footer">
          <form action="delete_item.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="delete-id" name="delete_id">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i>ยืนยัน</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- ส่งข้อมูลจาก PHP ไปยัง JS -->
  <script>
    window.stockItems = <?= $json_items ?>;
    window.stockEmployees = <?= $json_employees ?>;
    window.csrfToken      = "<?= $_SESSION['csrf_token'] ?>";
  </script>

  <!-- โหลดไฟล์ JS แยก -->
  <script src="assets/js/index.js" defer></script>
</body>

</html>