<?php
declare(strict_types=1);

// Normalise request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$requestUri = '/' . ltrim(preg_replace('#/{2,}#', '/', $requestUri), '/');

$documentRoot = __DIR__;
$filePath     = $documentRoot . $requestUri;

// Serve static files directly
if ($requestUri !== '/' && is_file($filePath)) {
    $realPath = realpath($filePath);
    if ($realPath !== false && strpos($realPath, realpath($documentRoot)) === 0) {
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'=>'text/css', 'js'=>'application/javascript', 'json'=>'application/json',
            'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif',
            'svg'=>'image/svg+xml', 'webp'=>'image/webp', 'ico'=>'image/x-icon',
            'woff'=>'font/woff', 'woff2'=>'font/woff2', 'ttf'=>'font/ttf',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        // header('Cache-Control: public, max-age=31536000, immutable');
          header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($realPath);
        exit;
    }
}

// Fall back to the actual viewer
require __DIR__ . '/index.php';