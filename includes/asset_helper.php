<?php

declare(strict_types=1);

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        $path = ltrim($path, '/');
        $baseDir = dirname(__DIR__);
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $path;

        if (is_file($fullPath)) {
            $version = filemtime($fullPath);
            return $path . '?v=' . $version;
        }

        return $path;
    }
}
