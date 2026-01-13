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

// จัดการแผนก
if (isset($_POST['add_dept']) && !empty(trim($_POST['dept_name']))) {
  if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $name = trim($_POST['dept_name']);
    try {
      $pdo->prepare("INSERT INTO departments (name) VALUES (?)")->execute([$name]);
      $_SESSION['toast'] = ['type' => 'success', 'message' => "เพิ่มแผนก". " " .$name. " " ."เรียบร้อย"];
    } catch (Exception $e) {
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'ชื่อแผนก'. " " .$name. " " .'ซ้ำ'];
    }
  }
  header('Location: manage_employees.php');
  exit;
}

if (isset($_GET['del_dept'])) {
  $id = (int)$_GET['del_dept'];
  $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
  $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบแผนกเรียบร้อย'];
  header('Location: manage_employees.php');
  exit;
}

// จัดการบริษัท
if (isset($_POST['add_company']) && !empty(trim($_POST['company_name']))) {
  if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $name = trim($_POST['company_name']);
    try {
      $pdo->prepare("INSERT INTO companies (name) VALUES (?)")->execute([$name]);
      $_SESSION['toast'] = ['type' => 'success', 'message' => "เพิ่มบริษัท". " " .$name. " " ."เรียบร้อย"];
    } catch (Exception $e) {
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'ชื่อบริษัท'. " " .$name. " " .'ซ้ำ'];
    }
  }
  header('Location: manage_employees.php');
  exit;
}

if (isset($_GET['del_company'])) {
  $id = (int)$_GET['del_company'];
  $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$id]);
  $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบบริษัทเรียบร้อย'];
  header('Location: manage_employees.php');
  exit;
}

// จัดการพนักงาน
if ($_POST && isset($_POST['action'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
  } else {
    $name = trim($_POST['name']);
    $dept = $_POST['department_id'] === '' ? null : (int)$_POST['department_id'];
    if ($name === '') {
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'กรุณากรอกชื่อ'];
    } else {
      try {
        if ($_POST['action'] === 'add') {
          $pdo->prepare("INSERT INTO employees (name, department_id) VALUES (?,?)")
            ->execute([$name, $dept]);
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มพนักงาน'. " " .$name. " " .'เรียบร้อย'];
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['id'])) {
          $id = (int)$_POST['id'];
          $pdo->prepare("UPDATE employees SET name=?, department_id=? WHERE id=?")
            ->execute([$name, $dept, $id]);
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขพนักงาน'. " " .$name. " " .'เรียบร้อย'];
        }
      } catch (Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด'];
      }
    }
  }
  header('Location: manage_employees.php');
  exit;
}

if (isset($_GET['delete_emp'])) {
  $id = (int)$_GET['delete_emp'];
  $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
  $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบพนักงานเรียบร้อย'];
  header('Location: manage_employees.php');
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
    <h2 class="mb-4 text-center">จัดการข้อมูลองค์กร</h2>
    <!-- แผนก -->
    <div class="row">
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0 text-center"><i class="bi bi-gear-fill me-1"></i>รายชื่อแผนกทั้งหมด</h5>
          </div>
          <div class="card-body">
            <form method="post" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="dept_name" class="form-control" placeholder="ชื่อแผนก" required>
              <button type="submit" name="add_dept" class="btn btn-primary"><i class="bi bi-send-plus"></i></button>
            </form>
            <div class="list-group scrollable-list">
              <?php foreach ($departments as $d): ?>
                <div class="list-group-item">
                  <span><?= htmlspecialchars($d['name']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick='openDelete("dept", <?= $d['id'] ?>, "<?= addslashes(htmlspecialchars($d['name'])) ?>")'>
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
            <form method="post" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="company_name" class="form-control" placeholder="ชื่อบริษัท" required>
              <button type="submit" name="add_company" class="btn btn-info text-white"><i class="bi bi-send-plus"></i></button>
            </form>
            <div class="list-group scrollable-list">
              <?php foreach ($companies as $c): ?>
                <div class="list-group-item">
                  <span><?= htmlspecialchars($c['name']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick='openDelete("company", <?= $c['id'] ?>, "<?= addslashes(htmlspecialchars($c['name'])) ?>")'>
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
                    <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick='openDelete("emp", <?= $e['id'] ?>, "<?= addslashes(htmlspecialchars($e['name'])) ?>")'>
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
        <form method="post">
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

  <!-- Modal ลบ (ใช้ร่วมกันทุกอย่าง: พนักงาน, แผนก, บริษัท) -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteModalLabel">
            <i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
          <strong id="deleteItemType" class="text-primary"></strong>
          <strong id="deleteItemName" class="text-danger mt-1"></strong>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
            <i class="bi bi-trash"></i> ยืนยัน
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container position-fixed top-0 end-0 p-3"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script src="assets/js/manage_employees.js"></script>
</body>

</html>