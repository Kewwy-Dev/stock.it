<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$redirect_url = ($_SESSION['user_role'] === 'admin') ? 'dashboard' : 'newuser';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loader - Welcome To Stock-IT</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= asset_url('assets/css/loader.css') ?>">
</head>

<body>

    <!-- Loader Screen -->
    <div class="d-flex flex-column justify-content-center align-items-center min-vh-100 loader-container">
        <div class="progress-wrapper">
            <div class="progress-container">
                <div class="progress-bar-custom" id="progressBar"></div>
            </div>
            <div class="progress-text" id="progressText">0%</div>
        </div>
    </div>

    <!-- หิมะตก - อยู่ตลอดเวลา -->
    <div class="snowflakes"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        window.redirectUrl = <?= json_encode($redirect_url) ?>;
    </script>

    <script src="<?= asset_url('assets/js/loader.js') ?>">
    </script>
</body>

</html>