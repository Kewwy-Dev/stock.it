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
           c.name AS company_name,
           ct.id   AS categories_id,
           ct.name AS categories_name
    FROM stock_transactions t
    LEFT JOIN items i ON t.item_id = i.id
    LEFT JOIN employees e ON t.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN companies c ON t.company_id = c.id
    LEFT JOIN categories ct ON i.category_id = ct.id
    ORDER BY t.transaction_date DESC, t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query("SELECT id, name FROM items ORDER BY name")->fetchAll();
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

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

    <!-- Filter Bar ใน history.php - แทนที่ส่วนเดิมทั้งหมด -->
    <div class="filter-bar mb-4 p-3 rounded-3 shadow-sm bg-white">
      <div class="row g-1 align-items-end">
        <!-- วันที่ -->
        <div class="col-12 col-md-3">
          <label class="form-label fw-medium">ช่วงวันที่</label>
          <div class="input-group">
            <input type="text" class="form-control flatpickr-input" id="filterDateRange" placeholder="เลือกช่วงวันที่" readonly>
            <span class="input-group-text bg-white border-start-0 cursor-pointer" onclick="fp?.open();">
              <i class="bi bi-calendar-event text-primary"></i>
            </span>
          </div>
        </div>

        <!-- หมวดหมู่ -->
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label fw-medium">หมวดหมู่</label>
          <div class="dropdown">
            <button class="btn border d-flex align-items-center justify-content-between w-100 custom-filter-btn"
              type="button" id="categoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap-fill text-primary fs-5"></i>
                <span id="categoryLabel" class="text-truncate">— หมวดหมู่สินค้า —</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end w-100 shadow" aria-labelledby="categoryDropdown" style="max-height: 320px; overflow-y: auto;">
              <li><a class="dropdown-item" href="#" data-value="">— หมวดหมู่สินค้า —</a></li>
              <?php foreach ($categories as $ct): ?>
                <li><a class="dropdown-item" href="#" data-value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['name']) ?></a></li>
              <?php endforeach; ?>
              <li>
                <hr class="dropdown-divider my-1">
              </li>
              <li><a class="dropdown-item text-danger fw-medium" href="#" id="clearCategory">— ล้าง —</a></li>
            </ul>
          </div>
          <input type="hidden" id="filterCategory" value="">
        </div>

        <!-- อุปกรณ์ -->
        <div class="col-12 col-md-3">
          <label class="form-label fw-medium">อุปกรณ์</label>
          <div class="dropdown">
            <button class="btn border d-flex align-items-center justify-content-between w-100 custom-filter-btn"
              type="button" id="itemDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-search text-primary fs-5"></i>
                <span id="itemLabel" class="text-truncate">— ค้นหาอุปกรณ์ —</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end w-100 shadow" aria-labelledby="itemDropdown" style="max-height: 320px; overflow-y: auto;">
              <li><a class="dropdown-item" href="#" data-value="">— ค้นหาอุปกรณ์ —</a></li>
              <?php foreach ($items as $item): ?>
                <li><a class="dropdown-item" href="#" data-value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></a></li>
              <?php endforeach; ?>
              <li>
                <hr class="dropdown-divider my-1">
              </li>
              <li><a class="dropdown-item text-danger fw-medium" href="#" id="clearItem">— ล้าง —</a></li>
            </ul>
          </div>
          <input type="hidden" id="filterItem" value="">
        </div>

        <!-- ประเภท -->
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label fw-medium">ประเภท</label>
          <div class="dropdown">
            <button class="btn border d-flex align-items-center justify-content-between w-100 custom-filter-btn"
              type="button" id="typeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left-right text-primary fs-5"></i>
                <span id="typeLabel" class="text-truncate">— ทุกประเภท —</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end w-100 shadow" aria-labelledby="typeDropdown">
              <li><a class="dropdown-item" href="#" data-value="">— ทุกประเภท —</a></li>
              <li><a class="dropdown-item" href="#" data-value="IN">เข้า</a></li>
              <li><a class="dropdown-item" href="#" data-value="OUT">ออก</a></li>
              <li>
                <hr class="dropdown-divider my-1">
                </hr>
              <li><a class="dropdown-item text-danger fw-medium" href="#" id="clearType">— ล้าง —</a></li>
            </ul>
          </div>
          <input type="hidden" id="filterType" value="">
        </div>

        <!-- บริษัท -->
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label fw-medium">บริษัท</label>
          <div class="dropdown">
            <button class="btn border d-flex align-items-center justify-content-between w-100 custom-filter-btn"
              type="button" id="companyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary fs-5"></i>
                <span id="companyLabel" class="text-truncate">— ทุกบริษัท —</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end w-100 shadow" aria-labelledby="companyDropdown" style="max-height: 320px; overflow-y: auto;">
              <li><a class="dropdown-item" href="#" data-value="">— ทุกบริษัท —</a></li>
              <?php foreach ($companies as $c): ?>
                <li><a class="dropdown-item" href="#" data-value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
              <?php endforeach; ?>
              <li>
                <hr class="dropdown-divider my-1">
              </li>
              <li><a class="dropdown-item text-danger fw-medium" href="#" id="clearCompany">— ล้าง —</a></li>
            </ul>
          </div>
          <input type="hidden" id="filterCompany" value="">
        </div>

        <!-- ปุ่มล้างทั้งหมด -->
        <div class="clearfilter col-12 col-md-3 col-lg-12 mt-lg-3">
          <button id="clearFilterBtn" class="btn btn-outline-danger w-100">
            <i class="bi bi-arrow-repeat me-1"></i> ล้างตัวกรอง
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
            <thead class="table-light">
              <tr>
                <th class="border-top-0">วันที่</th>
                <th class="border-top-0">อุปกรณ์</th>
                <th class="border-top-0 text-center">จำนวน</th>
                <th class="border-top-0 text-center">คงเหลือ</th>
                <th class="border-top-0">ผู้เบิก/แผนก</th>
                <th class="border-top-0">บริษัท</th>
                <th class="border-top-0">หมายเหตุ</th>
                <th class="border-top-0" width="90">
                  <button id="exportBtn" class="btn btn-success btn-sm w-100 d-flex justify-content-center">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i>
                    <span class="d-none d-md-inline">Excel</span>
                  </button>
                </th>
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

  <!-- ส่งข้อมูลไป JS -->
  <script>
    window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
    window.historyTransactions = <?= $json_trans ?>;
  </script>

  <!-- โหลด JS แยก -->
  <script src="assets/js/history.js" defer></script>
  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="assets/js/toast.js"></script>
</body>

</html>