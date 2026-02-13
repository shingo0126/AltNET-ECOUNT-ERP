<?php
// ★ Output Buffering 강제 활성화 (PHP 빌트인 서버용)
if (!ob_get_level()) {
    ob_start();
}

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve diagnose.php directly (진단 도구)
if (basename($path) === 'diagnose.php' && file_exists(__DIR__ . '/diagnose.php')) {
    require __DIR__ . '/diagnose.php';
    return true;
}

// Serve static files directly
if (preg_match('/\.(css|js|png|jpg|gif|ico|svg|woff2?|ttf|eot)$/', $path)) {
    $file = __DIR__ . $path;
    if (file_exists($file)) {
        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
        ];
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($file);
        return true;
    }
}

// All other requests go to index.php
require __DIR__ . '/index.php';
