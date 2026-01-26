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

// API Key จาก session (หรือจากฐานข้อมูลถ้ามี)
$removebg_api_key = $_SESSION['removebg_api_key'] ?? 'QHUChLWWDZRUAaNRCBgovwzh'; // ค่าเริ่มต้น
$credits_remaining = null; // จะดึงจริงใน modal

// ฟังก์ชันช่วยดึงเครดิตจาก remove.bg (เรียกใช้ AJAX หรือตรงนี้ก็ได้)
function getRemoveBgCredits($key)
{
  $ch = curl_init('https://api.remove.bg/v1.0/credits');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $key],
  ]);
  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code === 200) {
    $data = json_decode($response, true);
    return $data['credits'] ?? null;
  }
  return null;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css">
</head>
<style>
  body {
    font-family: 'Kanit', 'Segoe UI', sans-serif;
  }
</style>

<body>
  <!-- Navbar -->
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
            <!-- Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>ข้อมูลส่วนตัว</a></li>
                <?php if ($user_role === 'admin'): ?>
                  <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-person-gear me-2"></i>จัดการผู้ใช้</a></li>
                  <?php if ($user_name = 'admin'): ?>
                    <li>
                      <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#manageRemoveBgModal">
                        <i class="bi bi-gear-wide-connected me-2"></i>API Remove.bg
                      </a>
                    </li>
                  <?php endif; ?>
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

  <!-- Modal จัดการ API Remove BG -->
  <div class="modal fade" id="manageRemoveBgModal" tabindex="-1" aria-labelledby="manageRemoveBgLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content border-0 shadow-lg rounded-4">
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title d-flex align-items-center gap-2" id="manageRemoveBgLabel">
            <i class="bi bi-gear-wide-connected"></i> จัดการ API Remove.bg
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-4">
            <label class="form-label fw-bold">API Key ปัจจุบัน</label>
            <div class="input-group">
              <input type="text" class="form-control" id="currentApiKey" value="<?= htmlspecialchars($removebg_api_key) ?>" readonly>
              <button class="btn btn-outline-secondary" type="button" id="copyApiKeyBtn">
                <i class="bi bi-copy"></i> คัดลอก
              </button>
            </div>
          </div>

          <form id="updateApiKeyForm">
            <div class="mb-3">
              <label for="newApiKey" class="form-label fw-bold">เปลี่ยน API Key ใหม่</label>
              <input type="text" class="form-control" id="newApiKey" placeholder="ใส่ API Key จาก https://www.remove.bg">
            </div>

            <a href="https://www.remove.bg/dashboard#api-key" target="_blank">รับAPI Keys ที่นี่</a>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-floppy-fill me-1"></i> บันทึก
          </button>
        </div>
        </form>
      </div>
    </div>
  </div>
</body>

</html>