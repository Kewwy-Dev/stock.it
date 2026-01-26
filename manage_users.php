<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';

session_start();

if (empty($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
  $_SESSION['toast'] = ['type' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
  header('Location: index.php');
  exit;
}

// ดึงรายชื่อแผนกสำหรับ modal แก้ไข
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// จัดการ POST สำหรับเปลี่ยน role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['role'])) {
  $userId = (int)$_POST['user_id'];
  $newRole = $_POST['role'] === 'admin' ? 'admin' : 'user';

  if ($userId === $_SESSION['user_id']) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่สามารถเปลี่ยนบทบาทของตัวเองได้'];
  } else {
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$newRole, $userId]);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'อัปเดตบทบาทผู้ใช้เรียบร้อย'];
  }
  header('Location: manage_users.php');
  exit;
}

// จัดการสร้างผู้ใช้ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
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
  header('Location: manage_users.php');
  exit;
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
  header('Location: manage_users.php');
  exit;
}

// จัดการแก้ไขผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
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
  header('Location: manage_users.php');
  exit;
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
  <title>จัดการผู้ใช้ - Stock-IT</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/manage_users.css">

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
                <tr>
                  <td><?= htmlspecialchars($row['id']) ?></td>
                  <td class="text-center">
                    <?php if (!empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image'])): ?>
                      <img src="uploads/<?= htmlspecialchars($row['profile_image']) ?>" alt="โปรไฟล์" class="profile-thumb">
                    <?php else: ?>
                      <div class="profile-placeholder"></div>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($row['username']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td class="text-center">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                      <input type="hidden" name="role" value="user">
                      <div class="form-switch d-flex align-items-center justify-content-center">
                        <input class="form-check-input" type="checkbox" name="role" value="admin"
                          <?= $row['role'] === 'admin' ? 'checked' : '' ?>
                          <?= $row['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>
                          onchange="this.form.submit()">
                        <span class="ms-2 small text-muted">Admin</span>
                      </div>
                    </form>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                      <i class="bi bi-pencil-square fs-5"></i>
                    </button>
                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                      <button type="button" class="btn btn-link p-0 btn-delete"
                        data-username="<?= htmlspecialchars($row['username']) ?>"
                        data-delete-url="manage_users.php?delete=<?= $row['id'] ?>">
                        <i class="bi bi-trash fs-5 text-danger"></i>
                      </button>
                    <?php endif; ?>

                    <!-- Modal แก้ไขผู้ใช้ -->
                    <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">แก้ไขผู้ใช้: <?= htmlspecialchars($row['username']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <form method="post">
                              <input type="hidden" name="edit_user_id" value="<?= $row['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                              <div class="mb-3">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">อีเมล</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>" required>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">แผนก</label>
                                <select name="department_id" class="form-select">
                                  <option value="">— ไม่ระบุ —</option>
                                  <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $d['id'] == ($row['department_id'] ?? '') ? 'selected' : '' ?>>
                                      <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">รหัสผ่านใหม่ (ถ้าต้องการรีเซ็ต)</label>
                                <input type="password" name="new_password" class="form-control" minlength="6">
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
                      <div class="modal-dialog modal-lg">
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

                              <div class="row g-3">

                                <div class="col-md-12">
                                  <label class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                  <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="username" class="form-control" required placeholder="ใช้สำหรับล็อกอิน" autocomplete="off">
                                  </div>
                                </div>

                                <div class="col-md-12">
                                  <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                  <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required minlength="6">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                      <i class="bi bi-eye-slash"></i>
                                    </button>
                                  </div>
                                  <div class="form-text text-muted">ต้องมีอย่างน้อย 6 ตัวอักษร</div>
                                </div>

                                <div class="col-md-6">
                                  <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                  <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" name="name" class="form-control" required>
                                  </div>
                                </div>

                                <div class="col-md-6">
                                  <label class="form-label">อีเมล <span class="text-danger">*</span></label>
                                  <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required>
                                  </div>
                                </div>

                                <div class="col-12">
                                  <label class="form-label">แผนก (ไม่บังคับ)</label>
                                  <select name="department_id" class="form-select">
                                    <option value="">— ไม่ระบุแผนก —</option>
                                    <?php foreach ($departments as $d): ?>
                                      <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                  </select>
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
  <script src="assets/js/manage_users.js"></script>
  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="assets/js/toast.js"></script>
</body>

</html>