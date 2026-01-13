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

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $pdo->prepare("SELECT username, name, email, role, department_id, profile_image 
                       FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !is_array($user)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้ กรุณาเข้าสู่ระบบใหม่'];
    header('Location: logout.php');
    exit;
}

// ดึงรายชื่อแผนก
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// จัดการ POST
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = ['type' => 'danger', 'text' => 'Invalid CSRF token'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

            $profile_image = $user['profile_image'] ?? null;
            if (!empty($_FILES['profile_image']['name'])) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $allowed) && $_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                    $new_image = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = 'uploads/' . $new_image;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                        if ($profile_image && file_exists('uploads/' . $profile_image)) {
                            unlink('uploads/' . $profile_image);
                        }
                        $profile_image = $new_image;
                    } else {
                        $message = ['type' => 'danger', 'text' => 'อัปโหลดรูปภาพล้มเหลว'];
                    }
                } else {
                    $message = ['type' => 'danger', 'text' => 'รูปภาพไม่ถูกต้อง (jpg/png/gif/webp, ไม่เกิน 2MB)'];
                }
            }

            if (!$message) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, department_id = ?, profile_image = ? WHERE id = ?");
                $stmt->execute([$name, $email, $dept_id, $profile_image, $user_id]);

                $_SESSION['user_name'] = $name;
                $_SESSION['profile_image'] = $profile_image;

                $message = ['type' => 'success', 'text' => 'อัปเดตข้อมูลส่วนตัวเรียบร้อย'];

                // เพิ่มรีเฟรชอัตโนมัติหลังสำเร็จ
                echo '<script>
                        setTimeout(function() {
                            window.location.href = "profile.php";
                        }, 1500); // รีเฟรชหลัง 1.5 วินาที
                      </script>';
            }
        } elseif ($action === 'change_password') {
            $old_pass = $_POST['old_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
                $message = ['type' => 'danger', 'text' => 'กรุณากรอกข้อมูลให้ครบ'];
            } elseif ($new_pass !== $confirm_pass) {
                $message = ['type' => 'danger', 'text' => 'รหัสผ่านใหม่ไม่ตรงกัน'];
            } elseif (strlen($new_pass) < 6) {
                $message = ['type' => 'danger', 'text' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'];
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $stored_pass = $stmt->fetchColumn();

                if (password_verify($old_pass, $stored_pass)) {
                    $hashed_new = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_new, $user_id]);
                    $message = ['type' => 'success', 'text' => 'เปลี่ยนรหัสผ่านเรียบร้อย'];
                } else {
                    $message = ['type' => 'danger', 'text' => 'รหัสผ่านเก่าไม่ถูกต้อง'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว - Stock-IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/profile.css">

</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container profile-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-2">
                <h4 class="mb-0"><i class="bi bi-person-bounding-box me-2"></i>ข้อมูลส่วนตัว</h4>
            </div>

            <div class="card-body p-3 p-md-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message['text']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <!-- คอลัมน์ซ้าย -->
                    <div class="col-lg-5">
                        <div class="sidebar">
                            <div class="mb-2 text-center">
                                <h5 class="fw-bold mb-2 text-center"><?= htmlspecialchars($user['role']) ?></h5>
                                <div class="profile-img-wrapper" id="profileImageWrapper">
                                    <?php if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="รูปโปรไฟล์" class="profile-img" id="profilePreview">
                                    <?php else: ?>
                                        <div class="profile-img-placeholder" id="profilePreview"><i class="bi bi-person-circle"></i></div>
                                    <?php endif; ?>
                                    <div class="profile-img-overlay">
                                        <i class="bi bi-camera-fill"></i>
                                    </div>
                                </div>
                            </div>

                            <h5 class="fw-bold mb-2 text-center"><?= htmlspecialchars($user['name'] ?? 'ผู้ใช้') ?></h5>
                            <p class="text-muted mb-3 text-center">@<?= htmlspecialchars($user['username'] ?? 'ไม่ระบุ') ?></p>

                            <!-- ฟอร์มอัปเดตข้อมูล (แก้ไข: ย้าย input file เข้ามาใน form) -->
                            <form method="post" enctype="multipart/form-data" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-2">
                                    <label class="form-label">ชื่อ-นามสกุล</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">แผนก</label>
                                    <select name="department_id" class="form-select">
                                        <option value="">— ไม่ระบุแผนก —</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= $d['id'] == ($user['department_id'] ?? '') ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- ย้าย input file เข้ามาใน form แต่ยังซ่อนไว้ -->
                                <input type="file" name="profile_image" id="profileImageInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">

                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-floppy me-1"></i>บันทึกข้อมูล</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- คอลัมน์ขวา: เปลี่ยนรหัสผ่าน -->
                    <div class="col-lg-7">
                        <div class="form-section">
                            <h5 class="mb-3 pb-2 border-bottom"><i class="bi bi-key me-1"></i>เปลี่ยนรหัสผ่าน</h5>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-2">
                                    <label class="form-label">รหัสผ่านเก่า</label>
                                    <div class="password-wrapper position-relative">
                                        <input type="password" name="old_password" id="oldPassword" class="form-control" required>
                                        <span class="toggle-password d-none" id="toggleOld">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">รหัสผ่านใหม่</label>
                                    <div class="password-wrapper position-relative">
                                        <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="6">
                                        <span class="toggle-password d-none" id="toggleNew">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <div class="password-wrapper position-relative">
                                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required>
                                        <span class="toggle-password d-none" id="toggleConfirm">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-key-fill me-1"></i>เปลี่ยนรหัสผ่าน</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/profile.js"></script>
</body>

</html>