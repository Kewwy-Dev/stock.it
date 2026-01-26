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

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ดึงข้อมูล
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$companies   = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$employees   = $pdo->query("
    SELECT e.id, e.name, e.department_id, d.name AS dept_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    ORDER BY d.name, e.name
")->fetchAll();

// จัดการการลบ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_type'])) {

  // Debug: บันทึกข้อมูลที่ได้รับ (ดูใน error_log หรือสร้างไฟล์ log ชั่วคราว)
  error_log("DELETE REQUEST RECEIVED: " . print_r($_POST, true));

  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    error_log("CSRF INVALID");
  } else {
    $type = $_POST['delete_type'] ?? '';
    $id   = (int)($_POST['delete_id'] ?? 0);

    if ($id <= 0 || !in_array($type, ['dept', 'company', 'emp'])) {
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง'];
      error_log("INVALID TYPE OR ID");
    } else {
      try {
        if ($type === 'dept') {
          $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
          $stmt->execute([$id]);
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบแผนกเรียบร้อย'];
        } elseif ($type === 'company') {
          $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
          $stmt->execute([$id]);
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบบริษัทเรียบร้อย'];
        } elseif ($type === 'emp') {
          $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
          $stmt->execute([$id]);
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบพนักงานเรียบร้อย'];
        }
        error_log("DELETE SUCCESS: $type ID $id");
      } catch (Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        error_log("DELETE ERROR: " . $e->getMessage());
      }
    }
  }

  // สำคัญ! redirect กลับมาเพื่อ reload ข้อมูลใหม่
  header("Location: manage_employees.php");
  exit;
}
// จัดการเพิ่มแผนก
if (isset($_POST['add_dept']) && trim($_POST['dept_name']) !== '') {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast'] = [
      'type' => 'error',
      'message' => 'Invalid CSRF token (เพิ่มแผนก)'
    ];
  } else {
    $name = trim($_POST['dept_name']);
    try {
      $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
      $stmt->execute([$name]);
      $_SESSION['toast'] = [
        'type' => 'success',
        'message' => "เพิ่มแผนก <strong>$name</strong> เรียบร้อย"
      ];
    } catch (PDOException $e) {
      if ($e->getCode() == '23000') { // duplicate entry
        $_SESSION['toast'] = [
          'type' => 'error',
          'message' => "ชื่อแผนก <strong>$name</strong> มีอยู่แล้ว"
        ];
      } else {
        $_SESSION['toast'] = [
          'type' => 'error',
          'message' => "เกิดข้อผิดพลาดในการเพิ่มแผนก: " . $e->getMessage()
        ];
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}

// จัดการเพิ่มบริษัท
if (isset($_POST['add_company']) && trim($_POST['company_name']) !== '') {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast'] = [
      'type' => 'error',
      'message' => 'Invalid CSRF token (เพิ่มบริษัท)'
    ];
  } else {
    $name = trim($_POST['company_name']);
    try {
      $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
      $stmt->execute([$name]);
      $_SESSION['toast'] = [
        'type' => 'success',
        'message' => "เพิ่มบริษัท <strong>$name</strong> เรียบร้อย"
      ];
    } catch (PDOException $e) {
      if ($e->getCode() == '23000') { // duplicate entry
        $_SESSION['toast'] = [
          'type' => 'error',
          'message' => "ชื่อบริษัท <strong>$name</strong> มีอยู่แล้ว"
        ];
      } else {
        $_SESSION['toast'] = [
          'type' => 'error',
          'message' => "เกิดข้อผิดพลาดในการเพิ่มบริษัท: " . $e->getMessage()
        ];
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}

// จัดการเพิ่ม / แก้ไขพนักงาน (ปรับปรุงให้สมบูรณ์ขึ้น)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast'] = [
      'type' => 'error',
      'message' => 'Invalid CSRF token (จัดการพนักงาน)'
    ];
  } else {
    $name = trim($_POST['name'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    if ($name === '') {
      $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'กรุณากรอกชื่อพนักงาน'
      ];
    } else {
      try {
        if ($_POST['action'] === 'add') {
          $stmt = $pdo->prepare("INSERT INTO employees (name, department_id) VALUES (?, ?)");
          $stmt->execute([$name, $department_id]);
          $_SESSION['toast'] = [
            'type' => 'success',
            'message' => "เพิ่มพนักงาน <strong>$name</strong> เรียบร้อย"
          ];
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['id'])) {
          $id = (int)$_POST['id'];
          $stmt = $pdo->prepare("UPDATE employees SET name = ?, department_id = ? WHERE id = ?");
          $stmt->execute([$name, $department_id, $id]);
          $_SESSION['toast'] = [
            'type' => 'success',
            'message' => "แก้ไขข้อมูลพนักงาน <strong>$name</strong> เรียบร้อย"
          ];
        } else {
          $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'ไม่พบการกระทำที่ถูกต้อง'
          ];
        }
      } catch (PDOException $e) {
        $_SESSION['toast'] = [
          'type' => 'error',
          'message' => "เกิดข้อผิดพลาดในการจัดการพนักงาน: " . $e->getMessage()
        ];
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="th" <?php if (isset($_SESSION['toast'])): ?>data-toast='<?= json_encode($_SESSION['toast']) ?>' <?php unset($_SESSION['toast']);
                                                                                                          endif; ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการองค์กร - Stock-IT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/manage_employees.css">
</head>

<body class="bg-light">
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2 class="mb-4 text-center"><i class="bi bi-person-gear me-1"></i>จัดการข้อมูลองค์กร</h2>
    <!-- แผนก -->
    <div class="row">
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0 text-center"><i class="bi bi-gear-fill me-1"></i>รายชื่อแผนกทั้งหมด</h5>
          </div>
          <div class="card-body">
            <form method="post" action="manage_employees.php" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="dept_name" class="form-control" placeholder="ชื่อแผนก" required>
              <button type="submit" name="add_dept" class="btn btn-primary">
                <i class="bi bi-send-plus"></i>
              </button>
            </form>
            <div class="list-group scrollable-list">
              <?php foreach ($departments as $d): ?>
                <div class="list-group-item">
                  <span><?= htmlspecialchars($d['name']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                    data-type="dept"
                    data-id="<?= $d['id'] ?>"
                    data-name="<?= addslashes(htmlspecialchars($d['name'])) ?>">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <!-- บริษัท -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0 text-center"><i class="bi bi-building-fill me-1"></i>รายชื่อบริษัท</h5>
          </div>
          <div class="card-body">
            <form method="post" action="manage_employees.php" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="company_name" class="form-control" placeholder="ชื่อบริษัท" required>
              <button type="submit" name="add_company" class="btn btn-info text-white">
                <i class="bi bi-send-plus"></i>
              </button>
            </form>
            <div class="list-group scrollable-list">
              <?php foreach ($companies as $c): ?>
                <div class="list-group-item">
                  <span><?= htmlspecialchars($c['name']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                    data-type="company"
                    data-id="<?= $c['id'] ?>"
                    data-name="<?= addslashes(htmlspecialchars($c['name'])) ?>">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <!-- รายชื่อพนักงาน -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0 text-center"><i class="bi bi-person-vcard-fill me-1"></i>รายชื่อพนักงาน</h5>
          </div>
          <div class="card-body">
            <button class="btn btn-success w-100 mb-3" data-bs-toggle="modal" data-bs-target="#empModal" onclick="openAdd()">
              <i class="bi bi-person-fill-add me-1"></i>เพิ่มพนักงาน
            </button>
            <div class="list-group scrollable-list">
              <?php foreach ($employees as $e): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($e['name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($e['dept_name'] ?? 'ยังไม่ระบุแผนก') ?></small>
                  </div>
                  <div>
                    <button class="btn btn-sm btn-outline-primary me-1"
                      onclick='openEdit(<?= $e['id'] ?>,"<?= addslashes(htmlspecialchars($e['name'])) ?>",<?= $e['department_id'] ?: 'null' ?>)'>
                      <i class="bi bi-pencil-square me-1"></i>แก้ไข
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                      data-type="emp"
                      data-id="<?= $e['id'] ?>"
                      data-name="<?= addslashes(htmlspecialchars($e['name'])) ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal เพิ่มพนักงาน -->
  <div class="modal fade" id="empModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success">
          <h5 class="modal-title" id="empTitle">เพิ่มพนักงาน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" action="manage_employees.php">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="empAction" value="add">
            <input type="hidden" name="id" id="empId">
            <div class="mb-3">
              <label class="form-label">ชื่อ-สกุล</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">แผนก</label>
              <select name="department_id" class="form-select">
                <option value="">— ไม่ระบุแผนก —</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>บันทึก</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/manage_employees.js"></script>
  <script>
    window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
  </script>
  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="assets/js/toast.js"></script>
</body>

</html>