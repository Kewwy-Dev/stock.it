<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/asset_helper.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

if (!empty($_SESSION['user_id'])) {
  header('Location: index');
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $message = ['type' => 'error', 'text' => 'Invalid CSRF token'];
    log_event('auth', 'สมัครสมาชิกไม่สำเร็จ: CSRF token ไม่ถูกต้อง', [
      'username' => $_POST['username'] ?? '-'
    ]);
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $dept_id  = $_POST['department_id'] === '' ? null : (int)$_POST['department_id'];

    if ($username && $password && $name && $email) {
      if (strlen($password) < 6) {
        $message = ['type' => 'error', 'text' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'];
        log_event('auth', 'สมัครสมาชิกไม่สำเร็จ: รหัสผ่านสั้นเกินไป', [
          'username' => $username ?: '-'
        ]);
      } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
          $pdo->prepare("INSERT INTO users (username, password, name, email, role, department_id) VALUES (?, ?, ?, ?, 'user', ?)")
            ->execute([$username, $hashed, $name, $email, $dept_id]);
          log_event('auth', 'สมัครสมาชิกสำเร็จ', [
            'username' => $username,
            'email' => $email,
            'department_id' => $dept_id
          ]);
          $_SESSION['message'] = ['type' => 'success', 'text' => 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ'];
          header('Location: login');
          exit;
        } catch (PDOException $e) {
          if ($e->getCode() == 23000) {
            $message = ['type' => 'error', 'text' => 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว'];
            log_event('auth', 'สมัครสมาชิกไม่สำเร็จ: ชื่อผู้ใช้หรืออีเมลซ้ำ', [
              'username' => $username ?: '-',
              'email' => $email ?: '-'
            ]);
          } else {
            $message = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการสมัคร'];
            log_event('auth', 'สมัครสมาชิกไม่สำเร็จ: เกิดข้อผิดพลาด', [
              'username' => $username ?: '-'
            ]);
          }
        }
      }
    } else {
      $message = ['type' => 'error', 'text' => 'กรุณากรอกข้อมูลให้ครบ'];
      log_event('auth', 'สมัครสมาชิกไม่สำเร็จ: กรอกข้อมูลไม่ครบ', [
        'username' => $username ?: '-'
      ]);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock-IT • สมัครสมาชิก</title>
  <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('assets/css/register.css') ?>">

</head>

<body>
  <!-- หิมะตกพื้นหลัง -->
  <div class="snowflakes"></div>

  <div class="register-card">
    <div class="card-header">
      <h3><i class="bi bi-box-seam-fill me-2"></i>Stock-IT</h3>
      <p>สมัครสมาชิก</p>
    </div>
    <div class="card-body">
      <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] === 'error' ? 'danger' : 'success' ?> mb-3">
          <?= htmlspecialchars($message['text']) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
            <div class="icon-wrapper">
              <span class="input-icon"><i class="bi bi-person"></i></span>
              <input type="text" name="username" class="form-control" required>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
            <div class="icon-wrapper">
              <span class="input-icon"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" id="passwordRegister" class="form-control" required minlength="6">
              <span id="togglePasswordRegister" class="toggle-password d-none">
                <i class="bi bi-eye-slash"></i>
              </span>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
            <div class="icon-wrapper">
              <span class="input-icon"><i class="bi bi-person-badge"></i></span>
              <input type="text" name="name" class="form-control" required>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">อีเมล <span class="text-danger">*</span></label>
            <div class="icon-wrapper">
              <span class="input-icon"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control" required>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">แผนก (ไม่บังคับ)</label>
            <div class="dropdown">
              <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                type="button" data-bs-toggle="dropdown">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-diagram-3-fill text-success"></i>
                  <span class="dept-label">— ไม่ระบุแผนก —</span>
                </div>
                <i class="bi bi-chevron-down small text-muted"></i>
              </button>
              <ul class="dropdown-menu shadow animate-slide w-100">
                <li><a class="dropdown-item dept-select active" href="#" data-value="">— ไม่ระบุแผนก —</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <?php foreach ($departments as $d): ?>
                  <li>
                    <a class="dropdown-item dept-select" href="#" data-value="<?= $d['id'] ?>">
                      <?= htmlspecialchars($d['name']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
              <input type="hidden" name="department_id" class="dept-input" value="">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success w-100 mt-3">
          <i class="bi bi-person-plus-fill me-2"></i>สมัครสมาชิก
        </button>
      </form>

      <p class="text-center mt-3 mb-0">
        มีบัญชีแล้ว? <a href="login" class="text-link">เข้าสู่ระบบ</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= asset_url('assets/js/register.js') ?>"></script>
</body>

</html>

