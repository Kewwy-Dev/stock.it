<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/db.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

// ตรวจ CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid CSRF token']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$is_favorite = (int)($_POST['is_favorite'] ?? 0);

if ($id <= 0 || !in_array($is_favorite, [0,1])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid parameters']);
  exit;
}

try {
  $stmt = $pdo->prepare("UPDATE items SET is_favorite = ? WHERE id = ?");
  $stmt->execute([$is_favorite, $id]);

  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
  error_log($e->getMessage());
}