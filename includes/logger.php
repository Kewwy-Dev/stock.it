<?php
declare(strict_types=1);

function log_event(string $topic, string $message, array $context = []): void
{
    $base_dir = __DIR__ . '/../logs';
    if (!is_dir($base_dir)) {
        mkdir($base_dir, 0775, true);
    }

    $safe_topic = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $topic);
    if ($safe_topic === '' || $safe_topic === null) {
        $safe_topic = 'general';
    }

    $file_path = $base_dir . '/log_' . $safe_topic . '.log';
    $dt = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
    $timestamp = $dt->format('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = $_SESSION['username'] ?? 'guest';

    $parts = [];
    $parts[] = "หัวข้อ: {$topic}";
    $parts[] = "ข้อความ: {$message}";
    $parts[] = "ผู้ใช้: {$username}";
    $parts[] = "IP: {$ip}";

    if (!empty($context)) {
        $ctx_pairs = [];
        foreach ($context as $key => $value) {
            // ซ่อนข้อมูลที่เป็น id ตามที่กำหนด
            if ($key === 'id' || str_ends_with($key, '_id')) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $ctx_pairs[] = "{$key}=" . (string)$value;
            } else {
                $ctx_pairs[] = "{$key}=" . json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        if (!empty($ctx_pairs)) {
            $parts[] = 'ข้อมูลเพิ่มเติม: ' . implode(', ', $ctx_pairs);
        }
    }

    $line = '[' . $timestamp . '] ' . implode(' | ', $parts) . PHP_EOL;
    file_put_contents($file_path, $line, FILE_APPEND | LOCK_EX);
}
