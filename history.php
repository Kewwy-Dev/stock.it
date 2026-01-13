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

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- ลบรายการพร้อมปรับสต็อก ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
  } else {
    $tid = (int)$_POST['delete_id'];
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("SELECT item_id, type, quantity FROM stock_transactions WHERE id = ?");
      $stmt->execute([$tid]);
      $tr = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($tr) {
        $change = $tr['type'] === 'IN' ? -$tr['quantity'] : +$tr['quantity'];
        $pdo->prepare("UPDATE items SET stock = stock + ? WHERE id = ?")
          ->execute([$change, $tr['item_id']]);
        $pdo->prepare("DELETE FROM stock_transactions WHERE id = ?")->execute([$tid]);
        $pdo->commit();
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบรายการและปรับสต็อกเรียบร้อย'];
      } else {
        $pdo->rollBack();
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่พบรายการ'];
      }
    } catch (Exception $e) {
      $pdo->rollBack();
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }
  }
  header('Location: history.php');
  exit;
}

/* ---------- ดึงข้อมูลทั้งหมด ---------- */
$trans = $pdo->query("
    SELECT t.*,
           i.name AS item_name,
           e.name AS emp_name,
           d.name AS dept_name,
           c.name AS company_name
    FROM stock_transactions t
    LEFT JOIN items i ON t.item_id = i.id
    LEFT JOIN employees e ON t.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN companies c ON t.company_id = c.id
    ORDER BY t.transaction_date DESC, t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query("SELECT id, name FROM items ORDER BY name")->fetchAll();
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();

// JSON สำหรับ JavaScript (ปลอดภัย 100%)
$json_trans = json_encode($trans, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>
<!DOCTYPE html>
<html lang="th" <?php if (isset($_SESSION['toast'])): ?>data-toast='<?= json_encode($_SESSION['toast'], JSON_UNESCAPED_UNICODE) ?>' <?php unset($_SESSION['toast']);
                                                                                                                                  endif; ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ประวัติการใช้งาน - Stock-IT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
  <link rel="stylesheet" href="assets/css/history.css">
</head>

<body class="bg-light">
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2 class="mb-4 text-center">
      <i class="bi bi-clock-history me-2"></i>ประวัติการเบิก-รับเข้าอุปกรณ์
    </h2>

    <!-- Filter Bar -->
    <div class="filter-bar shadow-sm mb-4 p-3 bg-white rounded-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label mb-1 text-muted small fw-medium">ชื่ออุปกรณ์</label>
          <select id="filterItem" class="form-select form-select-sm">
            <option value="">ทุกอุปกรณ์</option>
            <?php foreach ($items as $it): ?>
              <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label mb-1 text-muted small fw-medium">ประเภท</label>
          <select id="filterType" class="form-select form-select-sm">
            <option value="">ทุกประเภท</option>
            <option value="IN">เพิ่มสต็อก</option>
            <option value="OUT">เบิกออก</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label mb-1 text-muted small fw-medium">บริษัท</label>
          <select id="filterCompany" class="form-select form-select-sm">
            <option value="">ทุกบริษัท</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label mb-1 text-muted small fw-medium">ช่วงวันที่</label>
          <div class="input-group input-group-sm">
            <input type="text" id="filterDateRange" class="form-control flatpickr-input" placeholder="เลือกช่วงวันที่" readonly>
            <span class="input-group-text bg-white border-start-0">
              <i class="bi bi-calendar3 text-muted"></i>
            </span>
          </div>
        </div>

        <div class="col-md-1">
          <button id="clearFilterBtn" class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-funnel me-1"></i>ล้าง
          </button>
        </div>

        <div class="col-md-2">
          <button id="exportBtn" class="btn btn-success btn-sm w-100">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-md-inline">ส่งออก Excel</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Filter Summary -->
    <div class="alert alert-info p-3 mb-4 shadow-sm d-none" id="filterSummary" role="alert">
      <div class="d-flex align-items-center">
        <i class="bi bi-check-circle-fill me-2 text-primary fs-5"></i>
        <div>
          <strong>พบ <span id="resultCount">0</span> รายการ</strong>
          <span id="filterDetails"></span>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-lg border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light sticky-top">
              <tr>
                <th class="border-top-0">วันที่</th>
                <th class="border-top-0">อุปกรณ์</th>
                <th class="border-top-0">ประเภท</th>
                <th class="border-top-0 text-center">จำนวน</th>
                <th class="border-top-0 text-center">คงเหลือ</th>
                <th class="border-top-0">ผู้เบิก/แผนก</th>
                <th class="border-top-0">บริษัท</th>
                <th class="border-top-0">หมายเหตุ</th>
                <th class="border-top-0" width="90"></th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr>
                <td colspan="9" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status"></div>
                </td>
              </tr>
            </tbody>
          </table>
          <div id="noData" class="text-center py-5 text-muted d-none">
            <i class="bi bi-journal-text display-1 opacity-50"></i>
            <h5 class="mt-3">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</h5>
            <p class="mb-0">ลองปรับตัวกรอง หรือกดปุ่ม "ล้าง" เพื่อดูข้อมูลทั้งหมด</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation" class="d-flex justify-content-center mt-4">
      <ul id="paginationContainer" class="pagination shadow-sm"></ul>
    </nav>
  </div>

  <!-- Delete Modal -->
  <div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>ยืนยันการลบ
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">ต้องการลบรายการนี้หรือไม่? การลบจะปรับสต็อกอัตโนมัติ</p>
          <div class="alert alert-warning p-2">
            <strong id="delInfo" class="text-danger"></strong>
          </div>
        </div>
        <div class="modal-footer border-0">
          <form method="post" class="d-flex w-100 justify-content-between gap-2">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="delete_id" id="delId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle me-1"></i>ยกเลิก
            </button>
            <button type="submit" class="btn btn-danger px-4">
              <i class="bi bi-trash3-fill me-2"></i>ลบถาวร
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1099"></div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

  <!-- ส่งข้อมูลไป JS -->
  <script>
    window.historyTransactions = <?= $json_trans ?>;
  </script>

  <!-- โหลด JS แยก -->
  <script src="assets/js/history.js" defer></script>
</body>

</html>