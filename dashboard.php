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

// ตัวเลือกช่วงเวลา
$range = $_GET['range'] ?? 'month';
$allowed_ranges = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($range, $allowed_ranges, true)) {
  $range = 'month';
}

$range_labels = [
  'today' => 'วันนี้',
  'week'  => 'สัปดาห์นี้',
  'month' => 'เดือนนี้',
  'year'  => 'ปีนี้',
  'all'   => 'ทั้งหมด'
];

$date_from = null;
$date_to = null;
$today = new DateTime('today');

switch ($range) {
  case 'today':
    $date_from = $today->format('Y-m-d');
    $date_to = $today->format('Y-m-d');
    break;
  case 'week':
    $date_from = (new DateTime('monday this week'))->format('Y-m-d');
    $date_to = (new DateTime('sunday this week'))->format('Y-m-d');
    break;
  case 'month':
    $date_from = (new DateTime('first day of this month'))->format('Y-m-d');
    $date_to = (new DateTime('last day of this month'))->format('Y-m-d');
    break;
  case 'year':
    $date_from = (new DateTime('first day of january this year'))->format('Y-m-d');
    $date_to = (new DateTime('last day of december this year'))->format('Y-m-d');
    break;
  case 'all':
  default:
    $date_from = null;
    $date_to = null;
    break;
}

$date_filter_sql = '';
$date_params = [];
if ($date_from && $date_to) {
  $date_filter_sql = " AND t.transaction_date >= :date_from AND t.transaction_date <= :date_to";
  $date_params = [':date_from' => $date_from, ':date_to' => $date_to];
}

// สถิติหลัก
$total_items = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM items WHERE stock < 5")->fetchColumn();
$out_of_stock = $pdo->query("SELECT COUNT(*) FROM items WHERE stock = 0")->fetchColumn();
$total_history = $pdo->query("SELECT COUNT(*) FROM stock_transactions")->fetchColumn();
$total_departments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$total_companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// ธุรกรรมล่าสุด
$recent_stmt = $pdo->prepare("
    SELECT t.id, t.type, t.quantity, t.transaction_date, i.name AS item_name,
           e.name AS emp_name, c.name AS company_name
    FROM stock_transactions t
    LEFT JOIN items i ON t.item_id = i.id
    LEFT JOIN employees e ON t.employee_id = e.id
    LEFT JOIN companies c ON t.company_id = c.id
    WHERE 1=1 $date_filter_sql
    ORDER BY t.created_at DESC LIMIT 20
");
$recent_stmt->execute($date_params);
$recent_trans = $recent_stmt->fetchAll();

// กราฟ: การเบิกตามแผนก
$stock_by_dept_stmt = $pdo->prepare("
    SELECT d.name AS dept_name, COALESCE(SUM(t.quantity), 0) AS total_out
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    LEFT JOIN stock_transactions t ON t.employee_id = e.id AND t.type = 'OUT' $date_filter_sql
    GROUP BY d.id, d.name
    ORDER BY total_out DESC
");
$stock_by_dept_stmt->execute($date_params);
$stock_by_dept = $stock_by_dept_stmt->fetchAll();

$chart_labels = array_column($stock_by_dept, 'dept_name');
$chart_data   = array_column($stock_by_dept, 'total_out');

// ดึงรายละเอียดสินค้าที่ถูกเบิกในแต่ละแผนก (สำหรับ tooltip)
$detail_stmt = $pdo->prepare("
    SELECT 
        i.name AS item_name,
        SUM(t.quantity) AS qty
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    LEFT JOIN stock_transactions t ON t.employee_id = e.id AND t.type = 'OUT' $date_filter_sql
    LEFT JOIN items i ON t.item_id = i.id
    WHERE d.name = :dept_name AND t.quantity IS NOT NULL
    GROUP BY i.name
    ORDER BY qty DESC
    LIMIT 10
");

$dept_details = [];
foreach ($stock_by_dept as $dept) {
  $detail_stmt->execute(array_merge($date_params, [':dept_name' => $dept['dept_name']]));
  $items = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
  $dept_details[$dept['dept_name']] = $items;
}

$json_labels = json_encode($chart_labels, JSON_UNESCAPED_UNICODE);
$json_data   = json_encode($chart_data);
$json_details = json_encode($dept_details, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แดชบอร์ด - Stock-IT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="assets/css/dashboard.css" rel="stylesheet">
</head>

<body>
  <?php include 'navbar.php'; ?>

  <div class="container-fluid dashboard-container">
    <h1 class="page-title">
      <i class="bi bi-speedometer2 me-2"></i>แดชบอร์ด Stock-IT
    </h1>

    <!-- ปุ่มเลือกช่วงเวลา -->
    <div class="range-selector">
      <?php foreach ($range_labels as $key => $label): ?>
        <a href="?range=<?= $key ?>" class="btn range-btn <?= $range === $key ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="text-center text-muted small mb-4">
      แสดงข้อมูล: <strong><?= $range_labels[$range] ?></strong>
    </div>

    <!-- สถิติหลัก - แก้โครงสร้างให้ถูกต้องและคลิกได้ -->
    <div class="stats-grid">
      <a href="index.php" class="stat-card-link">
        <div class="stat-card primary">
          <div class="stat-icon text-primary"><i class="bi bi-box-seam-fill"></i></div>
          <div class="stat-number"><?= number_format($total_items) ?></div>
          <div class="stat-label">อุปกรณ์ทั้งหมด</div>
        </div>
      </a>

      <a href="index.php?filter=low" class="stat-card-link">
        <div class="stat-card warning">
          <div class="stat-icon text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="stat-number"><?= number_format($low_stock) ?></div>
          <div class="stat-label">สต็อกต่ำ</div>
        </div>
      </a>

      <a href="index.php?filter=zero" class="stat-card-link">
        <div class="stat-card danger">
          <div class="stat-icon text-danger"><i class="bi bi-slash-circle-fill"></i></div>
          <div class="stat-number"><?= number_format($out_of_stock) ?></div>
          <div class="stat-label">หมดสต็อก</div>
        </div>
      </a>

      <a href="history.php" class="stat-card-link">
        <div class="stat-card dark">
          <div class="stat-icon text-dark"><i class="bi bi-clock-history"></i></div>
          <div class="stat-number"><?= number_format($total_history) ?></div>
          <div class="stat-label">ประวัติทำรายการ</div>
        </div>
      </a>

      <a href="manage_employees.php" class="stat-card-link">
        <div class="stat-card primary">
          <div class="stat-icon text-primary"><i class="bi bi-gear-fill"></i></div>
          <div class="stat-number"><?= number_format($total_departments) ?></div>
          <div class="stat-label">แผนก</div>
        </div>
      </a>

      <a href="manage_employees.php" class="stat-card-link">
        <div class="stat-card info">
          <div class="stat-icon text-info"><i class="bi bi-building-fill"></i></div>
          <div class="stat-number"><?= number_format($total_companies) ?></div>
          <div class="stat-label">บริษัท</div>
        </div>
      </a>

      <a href="manage_employees.php" class="stat-card-link">
        <div class="stat-card success">
          <div class="stat-icon text-success"><i class="bi bi-people-fill"></i></div>
          <div class="stat-number"><?= number_format($total_employees) ?></div>
          <div class="stat-label">รายชื่อพนักงาน</div>
        </div>
      </a>

      <a href="manage_users.php" class="stat-card-link">
        <div class="stat-card dark">
          <div class="stat-icon text-secondary"><i class="bi bi-person-fill"></i></div>
          <div class="stat-number"><?= number_format($total_users) ?></div>
          <div class="stat-label">จำนวนยูสเซอร์</div>
        </div>
      </a>
    </div>

    <!-- กราฟ + ธุรกรรมล่าสุด -->
    <div class="main-content">
      <!-- กราฟ -->
      <div class="card">
        <div class="card-header">
          <i class="bi bi-bar-chart-fill me-2"></i>การเบิกใช้อุปกรณ์ตามแผนก
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="stockChart"></canvas>
          </div>
        </div>
      </div>

      <!-- ธุรกรรมล่าสุด -->
      <div class="card">
        <div class="card-header">
          <i class="bi bi-clock-history me-2"></i>ธุรกรรมล่าสุด
        </div>
        <div class="card-body p-0">
          <?php if (empty($recent_trans)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-inbox opacity-50" style="font-size: 3rem;"></i>
              <p class="mt-3">ยังไม่มีธุรกรรมในช่วงเวลานี้</p>
            </div>
          <?php else: ?>
            <div class="transaction-scroll-container">
              <table class="table transaction-table mb-0">
                <thead>
                  <tr>
                    <th class="col-type">ประเภท</th>
                    <th class="col-item">รายการ</th>
                    <th class="col-quantity">จำนวน</th>
                    <th class="col-date">วันที่</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recent_trans as $trans): ?>
                    <tr>
                      <td class="col-type">
                        <span class="badge <?= $trans['type'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                          <?= $trans['type'] === 'IN' ? 'รับเข้า' : 'เบิกออก' ?>
                        </span>
                      </td>
                      <td class="col-item">
                        <div><strong><?= htmlspecialchars($trans['item_name']) ?></strong></div>
                        <small class="text-muted">
                          <?= $trans['emp_name'] ? 'โดย ' . htmlspecialchars($trans['emp_name']) : ($trans['company_name'] ? 'บริษัท ' . htmlspecialchars($trans['company_name']) : 'ปรับสต็อก') ?>
                        </small>
                      </td>
                      <td class="col-quantity">
                        <span class="<?= $trans['type'] === 'IN' ? 'quantity-in' : 'quantity-out' ?>">
                          <?= $trans['type'] === 'IN' ? '+' : '-' ?><?= number_format($trans['quantity']) ?>
                        </span>
                      </td>
                      <td class="col-date text-muted">
                        <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.dashboardChartLabels = <?= $json_labels ?? '[]' ?>;
    window.dashboardChartData = <?= $json_data   ?? '[]' ?>;
    window.dashboardDeptDetails = <?= $json_details ?? '{}' ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- โหลด Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <!-- โหลดไฟล์ JS ของเรา (สำคัญ: ต้องอยู่หลังตัวแปร window) -->
  <script src="assets/js/dashboard.js" defer></script>
  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="assets/js/toast.js"></script>
</body>

</html>