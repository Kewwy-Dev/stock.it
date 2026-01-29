<?php
session_start();
session_destroy();
header('Location: login?msg=logout');
exit;
?>