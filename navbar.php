<?php
// ตรวจสอบ session ก่อนแสดง navbar
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// ดึงรูปโปรไฟล์จาก session (ถ้ามี)
$profile_image = $_SESSION['profile_image'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'ผู้ใช้';
$user_role = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<style>
  body{
    font-family: 'Kanit', 'Segoe UI', sans-serif;
  }
</style>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="dashboard.php"><i class="bi bi-box-fill me-1"></i>Stock-IT</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <li class="nav-item">
              <a class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>" href="index.php">
                <i class="bi bi-box-fill me-1"></i>อุปกรณ์คงคลัง
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $current_page === 'history' ? 'active' : '' ?>" href="history.php">
                <i class="bi bi-clock-history me-1"></i>ประวัติทำรายการ
              </a>
            </li>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
              <li class="nav-item">
                <a class="nav-link <?= $current_page === 'manage_employees' ? 'active' : '' ?>" href="manage_employees.php">
                  <i class="bi bi-person-fill-gear me-1"></i>จัดการองค์กร
                </a>
              </li>
            <?php endif; ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>ข้อมูลส่วนตัว</a></li>
                <?php if ($user_role === 'admin'): ?>
                  <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-person-gear me-2"></i>จัดการผู้ใช้</a></li>
                <?php endif; ?>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="register.php"><i class="bi bi-person-add me-1"></i>สมัครสมาชิก</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</body>

</html>