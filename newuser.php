<?php
require_once __DIR__ . '/includes/asset_helper.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock-IT</title>
    <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('assets/css/newuser.css') ?>">
    
</head>
<body>
    <div class="access-denied-container">
        <div class="icon-wrapper">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        
        <h1>กรุณาติดต่อผู้ดูแลระบบ<br>เพื่อขอสิทธิ์ในการเข้าถึง</h1>
        
        <p>
            คุณไม่มีสิทธิ์เข้าถึงหน้านี้ในขณะนี้<br>
            กรุณาติดต่อผู้ดูแลระบบเพื่อขอสิทธิ์การใช้งานเพิ่มเติม
        </p>
        
        <a href="logout" class="btn btn-primary btn-back">
            <i class="bi bi-box-arrow-in-left me-2"></i>ออกจากระบบ
        </a>
    </div>
</body>
</html>
