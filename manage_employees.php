<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/asset_helper.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  error_log("POST received - action: " . ($_POST['action'] ?? 'none') . " | php://input: " . file_get_contents('php://input'));
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
$dept_map = [];
foreach ($departments as $d) {
  $dept_map[$d['id']] = $d['name'];
}

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function json_response($success, $message = '', $extra = [])
{
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge([
    'success' => $success,
    'message' => $message,
  ], $extra));
  exit;
}

// จัดการเพิ่ม/แก้ไขพนักงานจาก Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if ($is_ajax) {
      json_response(false, 'Invalid CSRF token');
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    header("Location: manage_employees.php");
    exit;
  }

  $name = trim($_POST['name'] ?? '');
  $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

  if ($name === '') {
    if ($is_ajax) {
      json_response(false, 'กรุณากรอกชื่อพนักงาน');
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'กรุณากรอกชื่อพนักงาน'];
    header("Location: manage_employees.php");
    exit;
  }

  // ตรวจสอบซ้ำ (ชื่อ + department_id)
  $stmtCheck = $pdo->prepare("SELECT id FROM employees WHERE LOWER(TRIM(name)) = LOWER(?) AND department_id <=> ?");
  $stmtCheck->execute([$name, $department_id]);
  $existing = $stmtCheck->fetch();

  if ($existing) {
    // ถ้าเป็นการแก้ไข ให้ข้ามตัวเอง
    if ($_POST['action'] === 'edit' && (int)$_POST['id'] === (int)$existing['id']) {
      // OK, เป็นตัวเอง
    } else {
      if ($is_ajax) {
        json_response(false, "ชื่อ {$name} (แผนกนี้) มีอยู่แล้วในระบบ");
      }
      $_SESSION['toast'] = ['type' => 'error', 'message' => "ชื่อ <strong>$name</strong> (แผนกนี้) มีอยู่แล้วในระบบ"];
      header("Location: manage_employees.php");
      exit;
    }
  }

  try {
    if ($_POST['action'] === 'add') {
      $stmt = $pdo->prepare("INSERT INTO employees (name, department_id) VALUES (?, ?)");
      $stmt->execute([$name, $department_id]);
      $new_id = (int)$pdo->lastInsertId();
      if ($is_ajax) {
        json_response(true, "เพิ่มพนักงาน {$name} เรียบร้อย", [
          'employee' => [
            'id' => $new_id,
            'name' => $name,
            'department_id' => $department_id,
            'department_name' => $department_id ? ($dept_map[$department_id] ?? '') : null,
          ],
          'action' => 'add'
        ]);
      }
      $_SESSION['toast'] = ['type' => 'success', 'message' => "เพิ่มพนักงาน <strong>$name</strong> เรียบร้อย"];
    } elseif ($_POST['action'] === 'edit' && !empty($_POST['id'])) {
      $id = (int)$_POST['id'];
      $stmt = $pdo->prepare("UPDATE employees SET name = ?, department_id = ? WHERE id = ?");
      $stmt->execute([$name, $department_id, $id]);
      if ($is_ajax) {
        json_response(true, "แก้ไขข้อมูลพนักงาน {$name} เรียบร้อย", [
          'employee' => [
            'id' => $id,
            'name' => $name,
            'department_id' => $department_id,
            'department_name' => $department_id ? ($dept_map[$department_id] ?? '') : null,
          ],
          'action' => 'edit'
        ]);
      }
      $_SESSION['toast'] = ['type' => 'success', 'message' => "แก้ไขข้อมูลพนักงาน <strong>$name</strong> เรียบร้อย"];
    }
  } catch (PDOException $e) {
    if ($is_ajax) {
      json_response(false, "เกิดข้อผิดพลาด: " . $e->getMessage());
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()];
  }

  header("Location: manage_employees.php");
  exit;
}
// จัดการการลบ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_type'])) {
  error_log("DELETE REQUEST RECEIVED: " . print_r($_POST, true));

  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if ($is_ajax) {
      json_response(false, 'Invalid CSRF token');
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    error_log("CSRF INVALID");
  } else {
    $type = $_POST['delete_type'] ?? '';
    $id   = (int)($_POST['delete_id'] ?? 0);

    if ($id <= 0 || !in_array($type, ['dept', 'company', 'emp'])) {
      if ($is_ajax) {
        json_response(false, 'ข้อมูลไม่ถูกต้อง');
      }
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง'];
      error_log("INVALID TYPE OR ID");
    } else {
      try {
        if ($type === 'dept') {
          $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
          $stmt->execute([$id]);
          if ($is_ajax) {
            json_response(true, 'ลบแผนกเรียบร้อย', ['type' => 'dept', 'id' => $id]);
          }
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบแผนกเรียบร้อย'];
        } elseif ($type === 'company') {
          $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
          $stmt->execute([$id]);
          if ($is_ajax) {
            json_response(true, 'ลบบริษัทเรียบร้อย', ['type' => 'company', 'id' => $id]);
          }
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบบริษัทเรียบร้อย'];
        } elseif ($type === 'emp') {
          $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
          $stmt->execute([$id]);
          if ($is_ajax) {
            json_response(true, 'ลบพนักงานเรียบร้อย', ['type' => 'emp', 'id' => $id]);
          }
          $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบพนักงานเรียบร้อย'];
        }
        error_log("DELETE SUCCESS: $type ID $id");
      } catch (Exception $e) {
        if ($is_ajax) {
          json_response(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        error_log("DELETE ERROR: " . $e->getMessage());
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}

// จัดการเพิ่มแผนก
if (isset($_POST['add_dept']) && trim($_POST['dept_name']) !== '') {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if ($is_ajax) {
      json_response(false, 'Invalid CSRF token (เพิ่มแผนก)');
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token (เพิ่มแผนก)'];
  } else {
    $name = trim($_POST['dept_name']);
    try {
      $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
      $stmt->execute([$name]);
      $new_id = (int)$pdo->lastInsertId();
      if ($is_ajax) {
        json_response(true, "เพิ่มแผนก {$name} เรียบร้อย", ['id' => $new_id, 'name' => $name, 'type' => 'dept']);
      }
      $_SESSION['toast'] = ['type' => 'success', 'message' => "เพิ่มแผนก <strong>$name</strong> เรียบร้อย"];
    } catch (PDOException $e) {
      if ($e->getCode() == '23000') {
        if ($is_ajax) {
          json_response(false, "ชื่อแผนก {$name} มีอยู่แล้ว");
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => "ชื่อแผนก <strong>$name</strong> มีอยู่แล้ว"];
      } else {
        if ($is_ajax) {
          json_response(false, "เกิดข้อผิดพลาดในการเพิ่มแผนก: " . $e->getMessage());
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => "เกิดข้อผิดพลาดในการเพิ่มแผนก: " . $e->getMessage()];
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}

// จัดการเพิ่มบริษัท
if (isset($_POST['add_company']) && trim($_POST['company_name']) !== '') {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if ($is_ajax) {
      json_response(false, 'Invalid CSRF token (เพิ่มบริษัท)');
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token (เพิ่มบริษัท)'];
  } else {
    $name = trim($_POST['company_name']);
    try {
      $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
      $stmt->execute([$name]);
      $new_id = (int)$pdo->lastInsertId();
      if ($is_ajax) {
        json_response(true, "เพิ่มบริษัท {$name} เรียบร้อย", ['id' => $new_id, 'name' => $name, 'type' => 'company']);
      }
      $_SESSION['toast'] = ['type' => 'success', 'message' => "เพิ่มบริษัท <strong>$name</strong> เรียบร้อย"];
    } catch (PDOException $e) {
      if ($e->getCode() == '23000') {
        if ($is_ajax) {
          json_response(false, "ชื่อบริษัท {$name} มีอยู่แล้ว");
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => "ชื่อบริษัท <strong>$name</strong> มีอยู่แล้ว"];
      } else {
        if ($is_ajax) {
          json_response(false, "เกิดข้อผิดพลาดในการเพิ่มบริษัท: " . $e->getMessage());
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => "เกิดข้อผิดพลาดในการเพิ่มบริษัท: " . $e->getMessage()];
      }
    }
  }
  header("Location: manage_employees.php");
  exit;
}

// จัดการนำเข้าพนักงานจาก JSON (จาก JS fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($data['action']) && $data['action'] === 'import_employees') {
      while (ob_get_level() > 0) {
        ob_end_clean();
      }
      header('Content-Type: application/json; charset=utf-8');

      if (!isset($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
      }

      $employees_to_import = $data['employees'] ?? [];
      $duplicates = []; // เก็บรายชื่อที่ซ้ำ
      $prepared_data = []; // เก็บข้อมูลที่ผ่านการตรวจสอบแล้วรอ Insert

      try {
        $stmtCheckDept = $pdo->prepare("SELECT id FROM departments WHERE name = ? LIMIT 1");
        $stmtInsertDept = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmtCheckEmp = $pdo->prepare("SELECT id FROM employees WHERE LOWER(TRIM(name)) = LOWER(?) AND department_id <=> ?");

        foreach ($employees_to_import as $emp) {
          $name = trim($emp['name'] ?? '');
          $dept_name = trim($emp['department_name'] ?? '');
          if (empty($name)) continue;

          // 1. จัดการแผนก (เพื่อให้ได้ ID มาเช็คซ้ำพนักงาน)
          $dept_id = null;
          if ($dept_name !== '') {
            $stmtCheckDept->execute([$dept_name]);
            $row = $stmtCheckDept->fetch(PDO::FETCH_ASSOC);
            if ($row) {
              $dept_id = $row['id'];
            } else {
              $stmtInsertDept->execute([$dept_name]);
              $dept_id = $pdo->lastInsertId();
            }
          }

          // 2. ตรวจสอบข้อมูลซ้ำ
          $stmtCheckEmp->execute([$name, $dept_id]);
          if ($stmtCheckEmp->fetch()) {
            $duplicates[] = "- " . htmlspecialchars($name) . ($dept_name ? " (แผนก: $dept_name)" : "");
          } else {
            // ถ้าไม่ซ้ำ เก็บลงอาเรย์เตรียม Insert
            $prepared_data[] = ['name' => $name, 'dept_id' => $dept_id];
          }
        }

        // 3. ถ้าพบข้อมูลซ้ำแม้แต่รายการเดียว ให้แจ้ง Error รายชื่อทั้งหมดและไม่ Insert ใดๆ ทั้งสิ้น
        if (!empty($duplicates)) {
          $error_msg = "ไม่สามารถนำเข้าได้เนื่องจากพบข้อมูลซ้ำในระบบ " . count($duplicates) . " รายการ:<br>" . implode("<br>", $duplicates);
          echo json_encode(['success' => false, 'message' => $error_msg]);
          exit;
        }

        // 4. ถ้าไม่มีซ้ำเลย เริ่มทำการ Insert ข้อมูลทั้งหมด
        $pdo->beginTransaction();
        $stmtInsertEmp = $pdo->prepare("INSERT INTO employees (name, department_id) VALUES (?, ?)");
        foreach ($prepared_data as $row) {
          $stmtInsertEmp->execute([$row['name'], $row['dept_id']]);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'inserted' => count($prepared_data)]);
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาด: ' . $e->getMessage()]);
      }
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="th" <?php if (isset($_SESSION['toast'])): ?>data-toast='<?= json_encode($_SESSION['toast']) ?>' <?php unset($_SESSION['toast']);
                                                                                                          endif; ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock-IT • จัดการองค์กร</title>
  <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('assets/css/manage_employees.css') ?>">
</head>

<body class="bg-light">
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2 class="mb-4 text-center text-primary"><i class="bi bi-person-gear me-1"></i>จัดการข้อมูลองค์กร</h2>

    <div class="row">

      <!-- 1. รายชื่อพนักงาน -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-vcard-fill me-1"></i>รายชื่อพนักงาน</h5>
            <button type="button" class="btn btn-sm btn-light toggle-list-btn"
              data-target="employee-list" title="ซ่อน/แสดงรายการ">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="card-body">
            <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#empModal" onclick="openAdd()">
              <i class="bi bi-person-fill-add me-1"></i>เพิ่มพนักงาน
            </button>
            <button class="btn btn-outline-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#importCsvModal">
              <i class="bi bi-filetype-xlsx me-1"></i> นำเข้ารายชื่อพนักงาน
            </button>
            <!-- ค้นหารายชื่อพนักงาน -->
            <div class="search-wrapper w-100">
              <span class="search-icon" aria-hidden="true"><i class="bi bi-search text-success"></i></span>
              <input type="text" id="employeeSearch" class="form-control" placeholder="ค้นหาพนักงาน" aria-label="ค้นหาพนักงาน">
            </div>
            <!-- รายการชื่อพนักงาน -->
            <div id="employee-list" class="list-group scrollable-list d-none mt-1">
              <div id="employeeSearchEmpty" class="list-group-item text-center text-muted d-none">ไม่พบรายชื่อพนักงาน</div>
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

      <!-- 2. รายชื่อแผนก -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-diagram-3-fill me-1"></i>รายชื่อแผนกทั้งหมด</h5>
            <button type="button" class="btn btn-sm btn-light toggle-list-btn"
              data-target="dept-list" title="ซ่อน/แสดงรายการ">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="card-body">
            <form method="post" action="manage_employees.php" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="dept_name" class="form-control rounded-2" placeholder="ชื่อแผนก" required>
              <button type="submit" name="add_dept" class="btn btn-primary">
                <i class="bi bi-send-plus"></i>
              </button>
            </form>
            <div id="dept-list" class="list-group scrollable-list d-none">
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

      <!-- 3. รายชื่อบริษัท -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-building-fill me-1"></i>รายชื่อบริษัท</h5>
            <button type="button" class="btn btn-sm btn-light toggle-list-btn"
              data-target="company-list" title="ซ่อน/แสดงรายการ">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="card-body">
            <form method="post" action="manage_employees.php" class="input-group mb-3">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="text" name="company_name" class="form-control rounded-2" placeholder="ชื่อบริษัท" required>
              <button type="submit" name="add_company" class="btn btn-info text-white">
                <i class="bi bi-send-plus"></i>
              </button>
            </form>
            <div id="company-list" class="list-group scrollable-list d-none">
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

    </div>
  </div>

  <!-- Modal เพิ่ม/แก้ไขพนักงาน -->
  <div class="modal fade" id="empModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success">
          <h5 class="modal-title" id="empTitle">เพิ่มพนักงาน</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
              <div class="dropdown">
                <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                  type="button" data-bs-toggle="dropdown">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <span class="dept-label">— ไม่ระบุแผนก —</span>
                  </div>
                  <i class="bi bi-chevron-down small text-muted"></i>
                </button>
                <ul class="dropdown-menu shadow animate-slide w-100">
                  <li><a class="dropdown-item dept-select-emp active" href="#" data-value="">— ไม่ระบุแผนก —</a></li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <?php foreach ($departments as $d): ?>
                    <li><a class="dropdown-item dept-select-emp" href="#" data-value="<?= $d['id'] ?>"><?= $d['name'] ?></a></li>
                  <?php endforeach; ?>
                </ul>
                <input type="hidden" name="department_id" class="dept-input" value="">
              </div>
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

  <!-- ==================== Modal นำเข้า xlsx ==================== -->
  <div class="modal fade" id="importCsvModal" tabindex="-1" aria-labelledby="importCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="importCsvModalLabel">
            <i class="bi bi-filetype-xlsx me-2"></i>นำเข้าพนักงานจากไฟล์ Excel
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <strong>รูปแบบไฟล์ที่รองรับ</strong><br>
            Excel-> .xlsx .xls .csv<br>
            แถวแรกต้องเป็น header: <strong>ชื่อ</strong> และ <strong>แผนก</strong> (หรือชื่อใกล้เคียง เช่น Name, ชื่อ-สกุล, Department, แผนก)<br>
            ข้อมูลเริ่มแถวที่ 2 เป็นต้นไป
          </div>
          <div class="mb-3">
            <a href="files/ตัวอย่าง_import_employees.xlsx" class="btn btn-outline-primary btn-sm" download>
              <i class="bi bi-download me-1"></i> ดาวน์โหลดไฟล์ตัวอย่าง (.xlsx)
            </a>
          </div>

          <form id="importCsvForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
              <label for="csvFile" class="form-label">เลือกไฟล์</label>
              <input type="file" class="form-control" id="csvFile" accept=".xlsx,.xls,.csv" required>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg" id="btnImportCsv">
                <i class="bi bi-upload me-1"></i> นำเข้าข้อมูล
              </button>
            </div>
          </form>

          <!-- แสดงผล preview / error -->
          <div id="importPreview" class="mt-4 d-none">
            <h6>ตัวอย่างข้อมูล (10 แถวแรก)</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered" id="previewTable">
                <thead>
                  <tr>
                    <th>ชื่อ</th>
                    <th>แผนก</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="importCount" class="mt-2 fw-bold"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script>
    window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
    window.departmentMap = <?= json_encode($dept_map, JSON_UNESCAPED_UNICODE) ?>;

    // Toggle แสดง/ซ่อนรายการ
    document.addEventListener("click", function(e) {
      const btn = e.target.closest(".toggle-list-btn");
      if (!btn) return;

      const targetId = btn.getAttribute("data-target");
      const list = document.getElementById(targetId);
      if (!list) return;

      list.classList.toggle("d-none");

      // สลับไอคอน eye ↔ eye-slash
      const icon = btn.querySelector("i");
      if (icon) {
        if (icon.classList.contains("bi-eye")) {
          icon.classList.replace("bi-eye", "bi-eye-slash");
        } else {
          icon.classList.replace("bi-eye-slash", "bi-eye");
        }
      }
    });
  </script>

  <!-- รวมไฟล์ JS เดิมของคุณ -->
  <script src="<?= asset_url('assets/js/manage_employees.js') ?>"></script>
  <script src="<?= asset_url('assets/js/toast.js') ?>"></script>
</body>

</html>
