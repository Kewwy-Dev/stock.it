<?php
// removebg.php - API-only version (no UI, returns JSON with base64)

// เริ่ม session เพื่อดึง API Key จาก session (ที่บันทึกจาก modal)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ดึง API Key จาก session ถ้ามี มิเช่นนั้นใช้ค่าคงที่ fallback
$API_KEY = $_SESSION['removebg_api_key'] ?? 'QHUChLWWDZRUAaNRCBgovwzh';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image file uploaded']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload error: ' . $file['error']]);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$mime = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowed)) {
    http_response_code(415);
    echo json_encode(['error' => 'Supported formats: JPG, PNG, WebP only']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large (max 10MB)']);
    exit;
}

// เรียก remove.bg API
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.remove.bg/v1.0/removebg',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $API_KEY],
    CURLOPT_POSTFIELDS     => [
        'image_file'   => new CURLFile($file['tmp_name'], $mime, $file['name']),
        'size'         => 'auto',
        'format'       => 'auto',
        'crop'         => 'true',
        'crop_margin'  => '15',
        'scale'        => 'original'
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_msg = curl_error($ch);
curl_close($ch);

if ($http_code === 200) {
    // ส่ง JSON กับ base64 ของภาพโปร่งใส
    $base64 = base64_encode($response);
    echo json_encode([
        'success' => true,
        'base64'  => 'data:image/png;base64,' . $base64
    ]);
    exit;
} else {
    $json = json_decode($response, true) ?? [];
    $error_title = $json['errors'][0]['title'] ?? 'Unknown API error';
    
    if ($http_code === 429) {
        $error_title = 'API credit limit reached (429)';
    }
    if ($error_msg) {
        $error_title .= ' - ' . $error_msg;
    }

    http_response_code($http_code ?: 500);
    echo json_encode(['error' => $error_title]);
    exit;
}