<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/asset_helper.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

if (!empty($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $message = ['type' => 'error', 'text' => 'Invalid CSRF token'];
    log_event('login', 'เข้าสู่ระบบไม่สำเร็จ: CSRF token ไม่ถูกต้อง', ['username' => $username ?: '-']);
  } else {
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
      $stmt = $pdo->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
      $stmt->execute([$username]);
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        log_event('login', 'เข้าสู่ระบบสำเร็จ', [
          'user_id' => $user['id'],
          'username' => $user['username'],
          'role' => $user['role']
        ]);
        header('Location: loader.php');  // เปลี่ยนเป็น loader.php สำหรับทุก role
        exit;
      } else {
        $message = ['type' => 'error', 'text' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        log_event('login', 'เข้าสู่ระบบไม่สำเร็จ: ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', ['username' => $username ?: '-']);
      }
    } else {
      $message = ['type' => 'error', 'text' => 'กรุณากรอกข้อมูลให้ครบ'];
      log_event('login', 'เข้าสู่ระบบไม่สำเร็จ: กรอกข้อมูลไม่ครบ', ['username' => $username ?: '-']);
    }
  }
}?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock-IT • เข้าสู่ระบบ</title>
  <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      background-image: url(uploads/wallpaper.jpg);
      background-size: cover;
      background-repeat: no-repeat;
      background-position: center;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Kanit', 'Segoe UI', sans-serif;
      position: relative;
      overflow: hidden;
    }

    /* หิมะตกพื้นหลัง */
    .snowflakes {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
    }

    .snowflake {
      position: absolute;
      background: white;
      border-radius: 50%;
      opacity: 0.7;
      animation: snowfall linear infinite;
    }

    @keyframes snowfall {
      0% {
        transform: translateY(-100px) translateX(0) rotate(0deg);
        opacity: 0;
      }

      10% {
        opacity: 0.7;
      }

      100% {
        transform: translateY(calc(100vh + 100px)) translateX(var(--drift)) rotate(360deg);
        opacity: 0;
      }
    }

    .login-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
      max-width: 420px;
      width: 90%;
      overflow: hidden;
      transition: transform 0.3s ease;
      position: relative;
      z-index: 10;
    }

    .login-card:hover {
      transform: translateY(-5px);
    }

    .card-header {
      background: linear-gradient(90deg, #0d6efd, #0b5ed7);
      color: white;
      text-align: center;
      padding: 1rem 1.5rem;
    }

    .card-header h3 {
      margin: 0;
      font-weight: 600;
      font-size: 1.6rem;
    }

    .card-header p {
      margin: 0.5rem 0 0;
      opacity: 0.9;
    }

    .card-body {
      padding: 1.75rem;
    }

    .form-label {
      font-size: 0.9rem;
      font-weight: 500;
    }

    .form-control {
      border-radius: 8px;
      padding: 0.6rem 0.6rem 0.6rem 2.5rem;
      font-size: 0.95rem;
    }

    .icon-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 1.1rem;
      z-index: 10;
      pointer-events: none;
    }

    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6c757d;
      font-size: 1.1rem;
      z-index: 10;
    }

    .btn-primary {
      border-radius: 8px;
      padding: 0.7rem;
      font-size: 0.95rem;
    }

    .text-link {
      color: #0d6efd;
      text-decoration: none;
      font-weight: 500;
    }

    .text-link:hover {
      text-decoration: underline;
    }

    /* Responsive สำหรับมือถือ */
    @media (max-width: 576px) {
      .login-card {
        width: 95%;
      }

      .card-header h3 {
        font-size: 1.4rem;
      }
    }
  </style>
</head>

<body>
  <!-- หิมะตกพื้นหลัง -->
  <div class="snowflakes"></div>

  <div class="login-card">
    <div class="card-header">
      <h3><i class="bi bi-box-seam-fill me-2"></i>Stock-IT</h3>
      <p>เข้าสู่ระบบ</p>
    </div>
    <div class="card-body">
      <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message']['type'] === 'error' ? 'danger' : 'success' ?> mb-3">
          <?= htmlspecialchars($_SESSION['message']['text']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] === 'error' ? 'danger' : 'success' ?> mb-3">
          <?= htmlspecialchars($message['text']) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="mb-3">
          <label class="form-label">ชื่อผู้ใช้</label>
          <div class="icon-wrapper">
            <span class="input-icon"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">รหัสผ่าน</label>
          <div class="icon-wrapper">
            <span class="input-icon"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="passwordLogin" class="form-control" required>
            <span id="togglePasswordLogin" class="toggle-password d-none">
              <i class="bi bi-eye-slash"></i>
            </span>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
        </button>
      </form>

      <p class="text-center mt-3 mb-0">
        ยังไม่มีบัญชี? <a href="register.php" class="text-link">สมัครสมาชิก</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // สร้างหิมะตก
    const snowContainer = document.querySelector('.snowflakes');
    const numSnowflakes = window.innerWidth < 768 ? 60 : 100;

    for (let i = 0; i < numSnowflakes; i++) {
      const flake = document.createElement('div');
      flake.classList.add('snowflake');

      const size = 5 + Math.random() * 8;
      flake.style.width = `${size}px`;
      flake.style.height = `${size}px`;

      flake.style.left = `${Math.random() * 100}vw`;

      const duration = 5 + Math.random() * 5;
      flake.style.animationDuration = `${duration}s`;

      flake.style.animationDelay = `${Math.random() * duration}s`;

      const drift = (Math.random() - 0.5) * 180;
      flake.style.setProperty('--drift', `${drift}px`);

      flake.style.opacity = 0.1 + Math.random() * 0;

      snowContainer.appendChild(flake);
    }

    // Toggle Password
    const passwordLogin = document.getElementById('passwordLogin');
    const togglePasswordLogin = document.getElementById('togglePasswordLogin');

    passwordLogin.addEventListener('input', function() {
      if (this.value.length > 0) {
        togglePasswordLogin.classList.remove('d-none');
      } else {
        togglePasswordLogin.classList.add('d-none');
      }
    });

    togglePasswordLogin.addEventListener('click', function() {
      const type = passwordLogin.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordLogin.setAttribute('type', type);
      this.querySelector('i').classList.toggle('bi-eye');
      this.querySelector('i').classList.toggle('bi-eye-slash');
    });
  </script>
</body>

</html>



