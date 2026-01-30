<?php
session_start();
require_once __DIR__ . '/includes/logger.php';

log_event('logout', 'ออกจากระบบ', [
    'user_id' => $_SESSION['user_id'] ?? 'guest',
    'username' => $_SESSION['username'] ?? null
]);

session_destroy();
header('Location: login?msg=logout');
exit;
?>
