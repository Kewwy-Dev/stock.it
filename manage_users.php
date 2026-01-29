<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/asset_helper.php';

session_start();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sendJson($data)
{
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

$isAjax = (
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if (empty($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
  $_SESSION['toast'] = ['type' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
  header('Location: index.php');
  exit;
}

// ดึงรายชื่อแผนกสำหรับ modal แก้ไข
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$dept_map = [];
foreach ($departments as $d) {
  $dept_map[$d['id']] = $d['name'];
}

// จัดการ POST สำหรับเปลี่ยน role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ((isset($_POST['user_id']) && isset($_POST['role'])) || (isset($_POST['action']) && $_POST['action'] === 'update_role'))) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
      sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    header('Location: manage_users.php');
    exit;
  }

  $userId = (int)$_POST['user_id'];
  $newRole = $_POST['role'] === 'admin' ? 'admin' : 'user';

  if ($userId === $_SESSION['user_id']) {
    if ($isAjax) {
      sendJson(['success' => false, 'error' => '?????????????????????????????????']);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => '?????????????????????????????????'];
  } else {
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$newRole, $userId]);
    if ($isAjax) {
      sendJson(['success' => true, 'role' => $newRole]);
    }
    $_SESSION['toast'] = ['type' => 'success', 'message' => '??????????????????????????'];
  }

if (!$isAjax) {
    header('Location: manage_users.php');
    exit;
  }
}

// จัดการสร้างผู้ใช้ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
      sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    header('Location: manage_users.php');
    exit;
  }
  $username = trim($_POST['username'] ?? '');
  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $dept_id  = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

  // ตรวจสอบข้อมูล
  if (empty($username) || empty($name) || empty($email) || empty($password)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง'];
  } elseif (strlen($password) < 6) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'];
  } else {
    // ตรวจสอบว่า username ซ้ำหรือไม่
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
      $_SESSION['toast'] = ['type' => 'error', 'message' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว'];
    } else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("
                INSERT INTO users (username, name, email, password, role, department_id, created_at)
                VALUES (?, ?, ?, ?, 'user', ?, NOW())
            ");
      $stmt->execute([$username, $name, $email, $hashed, $dept_id]);

      $_SESSION['toast'] = ['type' => 'success', 'message' => 'สร้างผู้ใช้ใหม่เรียบร้อย'];
    }
  }
  if (!$isAjax) {
    header('Location: manage_users.php');
    exit;
  }
}

// จัดการลบผู้ใช้
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $deleteId = (int)$_GET['delete'];

  if ($deleteId === $_SESSION['user_id']) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่สามารถลบตัวเองได้'];
  } else {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$deleteId]);
    $image = $stmt->fetchColumn();
    if ($image && file_exists('uploads/' . $image)) {
      unlink('uploads/' . $image);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$deleteId]);

    $_SESSION['toast'] = ['type' => 'success', 'message' => 'ลบผู้ใช้เรียบร้อย'];
  }
  if (!$isAjax) {
    header('Location: manage_users.php');
    exit;
  }
}

// จัดการแก้ไขผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
      sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
    }
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid CSRF token'];
    header('Location: manage_users.php');
    exit;
  }
  $editId = (int)$_POST['edit_user_id'];
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
  $new_pass = $_POST['new_password'] ?? '';

  if (empty($name) || empty($email)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ'];
  } else {
    $updateSql = "UPDATE users SET name = ?, email = ?, department_id = ? WHERE id = ?";
    $updateParams = [$name, $email, $dept_id, $editId];

    if (!empty($new_pass)) {
      if (strlen($new_pass) < 6) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'];
        header('Location: manage_users.php');
        exit;
      }
      $hashed_new = password_hash($new_pass, PASSWORD_DEFAULT);
      $updateSql = "UPDATE users SET name = ?, email = ?, department_id = ?, password = ? WHERE id = ?";
      $updateParams = [$name, $email, $dept_id, $hashed_new, $editId];
    }

    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($updateParams);

    $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลผู้ใช้เรียบร้อย'];
  }
  if (!$isAjax) {
    header('Location: manage_users.php');
    exit;
  }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด (รวม profile_image)
$users = $pdo->query("SELECT u.id, u.username, u.name, u.email, u.role, u.profile_image, u.department_id 
                      FROM users u ORDER BY u.id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock-IT • จัดการผู้ใช้</title>
  <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('assets/css/manage_users.css') ?>">

</head>

<body>
  <?php include 'navbar.php'; ?>

  <div class="container manage-card">
    <div class="card shadow">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <h4 class="mb-0"><i class="bi bi-person-fill-gear me-2"></i>จัดการผู้ใช้</h4>
        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createUserModal">
          <i class="bi bi-person-plus me-1"></i> สร้างผู้ใช้ใหม่
        </button>
      </div>

      <div class="card-body p-4">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th class="text-center">รูปโปรไฟล์</th>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อ-นามสกุล</th>
                <th>อีเมล</th>
                <th class="text-center">บทบาทแอดมิน</th>
                <th class="text-center">การจัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $row): ?>
                <tr data-user-id="<?= htmlspecialchars($row['id']) ?>">
                  <td><?= htmlspecialchars($row['id']) ?></td>
                  <td class="text-center">
                    <?php if (!empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image'])): ?>
                      <img src="uploads/<?= htmlspecialchars($row['profile_image']) ?>" alt="โปรไฟล์" class="profile-thumb">
                    <?php else: ?>
                      <div class="profile-placeholder"></div>
                    <?php endif; ?>
                  </td>
                  <td class="cell-username"><?= htmlspecialchars($row['username']) ?></td>
                  <td class="cell-name"><?= htmlspecialchars($row['name']) ?></td>
                  <td class="cell-email"><?= htmlspecialchars($row['email']) ?></td>
                  <td class="text-center">
                    <form method="post" class="d-inline role-form">
                      <input type="hidden" name="action" value="update_role">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                      <input type="hidden" name="role" value="user">
                      <div class="form-switch d-flex align-items-center justify-content-center">
                        <input class="form-check-input role-toggle" type="checkbox" name="role" value="admin"
                          <?= $row['role'] === 'admin' ? 'checked' : '' ?>
                          <?= $row['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>
                          >
                        <span class="ms-2 small text-muted">Admin</span>
                      </div>
                    </form>
                  </td>
                  <td class="text-center">
                    <div class="d-flex justify-content-center">
                      <button type="button" class="btn btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                        <i class="bi bi-pencil-square fs-5"></i>
                      </button>
                      <?php if ($row['id'] != $_SESSION['user_id']): ?>
                        <button type="button" class="btn btn-link p-0 btn-delete"
                          data-username="<?= htmlspecialchars($row['username']) ?>"
                          data-user-id="<?= $row['id'] ?>"
                          data-csrf="<?= $_SESSION['csrf_token'] ?>">
                          <i class="bi bi-trash fs-5 text-danger"></i>
                        </button>
                      <?php endif; ?>
                    </div>

                    <!-- Modal แก้ไขผู้ใช้ -->
                    <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-person-fill-gear me-2"></i>แก้ไขผู้ใช้: <?= htmlspecialchars($row['username']) ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <form method="post" class="edit-user-form" data-user-id="<?= $row['id'] ?>">
                              <input type="hidden" name="edit_user_id" value="<?= $row['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                              <div class="mb-3">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <div class="field-with-icon">
                                  <i class="bi bi-person-badge field-icon"></i>
                                  <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">อีเมล</label>
                                <div class="field-with-icon">
                                  <i class="bi bi-envelope field-icon"></i>
                                  <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>" required>
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">แผนก</label>
                                <div class="dropdown">
                                  <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                                    type="button" data-bs-toggle="dropdown">
                                    <div class="d-flex align-items-center gap-2">
                                      <i class="bi bi-diagram-3-fill text-primary"></i>
                                      <span class="dept-label">
                                        <?= htmlspecialchars($dept_map[$row['department_id']] ?? '— ไม่ระบุ —') ?>
                                      </span>
                                    </div>
                                    <i class="bi bi-chevron-down small text-muted"></i>
                                  </button>
                                  <ul class="dropdown-menu shadow animate-slide w-100">
                                    <li>
                                      <a class="dropdown-item dept-select-edit <?= empty($row['department_id']) ? 'active' : '' ?>"
                                        href="#" data-value="">— ไม่ระบุ —</a>
                                    </li>
                                    <li>
                                      <hr class="dropdown-divider">
                                    </li>
                                    <?php foreach ($departments as $d): ?>
                                      <li>
                                        <a class="dropdown-item dept-select-edit <?= $d['id'] == ($row['department_id'] ?? '') ? 'active' : '' ?>"
                                          href="#" data-value="<?= $d['id'] ?>">
                                          <?= htmlspecialchars($d['name']) ?>
                                        </a>
                                      </li>
                                    <?php endforeach; ?>
                                  </ul>
                                  <input type="hidden" name="department_id" class="dept-input" value="<?= htmlspecialchars($row['department_id'] ?? '') ?>">
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">รหัสผ่านใหม่ (ถ้าต้องการรีเซ็ต)</label>
                                <div class="field-with-icon password-wrapper">
                                  <i class="bi bi-lock field-icon"></i>
                                  <input type="password" name="new_password" class="form-control" minlength="6">
                                  <span class="toggle-password d-none">
                                    <i class="bi bi-eye-slash"></i>
                                  </span>
                                </div>
                                <small class="text-muted">ปล่อยว่างถ้าไม่ต้องการเปลี่ยน</small>
                              </div>

                              <button type="submit" class="btn btn-primary w-100">บันทึกการแก้ไข</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Modal สร้างผู้ใช้ใหม่ -->
                    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                          <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createUserModalLabel">
                              <i class="bi bi-person-plus-fill me-2"></i>สร้างผู้ใช้ใหม่
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="post">
                            <div class="modal-body p-4">
                              <input type="hidden" name="action" value="create_user">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                              <div class="row g-3">

                                <div class="col-md-6">
                                  <label class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                  <div class="field-with-icon">
                                    <i class="bi bi-person field-icon"></i>
                                    <input type="text" name="username" class="form-control" required placeholder="ใช้สำหรับล็อกอิน" autocomplete="off">
                                  </div>
                                </div>

                                <div class="col-md-6">
                                  <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                  <div class="field-with-icon password-wrapper">
                                    <i class="bi bi-lock field-icon"></i>
                                    <input type="password" name="password" class="form-control" required minlength="6">
                                    <span class="toggle-password d-none">
                                      <i class="bi bi-eye-slash"></i>
                                    </span>
                                  </div>
                                  <div class="form-text text-muted">ต้องมีอย่างน้อย 6 ตัวอักษร</div>
                                </div>

                                <div class="col-md-6">
                                  <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                  <div class="field-with-icon">
                                    <i class="bi bi-person-badge field-icon"></i>
                                    <input type="text" name="name" class="form-control" required>
                                  </div>
                                </div>

                                <div class="col-md-6">
                                  <label class="form-label">อีเมล <span class="text-danger">*</span></label>
                                  <div class="field-with-icon">
                                    <i class="bi bi-envelope field-icon"></i>
                                    <input type="email" name="email" class="form-control" required>
                                  </div>
                                </div>

                                <div class="col-12">
                                  <label class="form-label">แผนก (ไม่บังคับ)</label>
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
                                      <li><a class="dropdown-item dept-select-create active" href="#" data-value="">— ไม่ระบุแผนก —</a></li>
                                      <li>
                                        <hr class="dropdown-divider">
                                      </li>
                                      <?php foreach ($departments as $d): ?>
                                        <li><a class="dropdown-item dept-select-create" href="#" data-value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></a></li>
                                      <?php endforeach; ?>
                                    </ul>
                                    <input type="hidden" name="department_id" class="dept-input" value="">
                                  </div>
                                </div>

                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                              <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i> สร้างผู้ใช้
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">ไม่มีผู้ใช้ในระบบ</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php if (isset($_SESSION['toast'])): ?>
    <div id="toast-data"
      data-type="<?= $_SESSION['toast']['type'] ?>"
      data-message="<?= htmlspecialchars(addslashes($_SESSION['toast']['message'])) ?>"
      style="display: none;"></div>
    <?php unset($_SESSION['toast']); ?>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
    window.departments = <?= json_encode($departments, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="<?= asset_url('assets/js/manage_users.js') ?>"></script>
  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="<?= asset_url('assets/js/toast.js') ?>"></script>
</body>

</html>
