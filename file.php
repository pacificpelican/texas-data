<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$requestedPath = normalize_storage_relative_path(trim((string) ($_GET['path'] ?? '')));
if ($requestedPath === '') {
    require __DIR__ . '/404.php';
    exit;
}

$projectRoot = realpath(APP_ROOT);
$storageRoot = realpath(app_path('storage'));
$fullPath = realpath(app_path($requestedPath));

if ($projectRoot === false || $storageRoot === false || $fullPath === false || !is_file($fullPath) || strpos($fullPath, $storageRoot) !== 0) {
    require __DIR__ . '/404.php';
    exit;
}

$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $mime = mime_content_type($fullPath) ?: $mime;
} elseif (function_exists('finfo_open')) {
    $info = finfo_open(FILEINFO_MIME_TYPE);
    if ($info !== false) {
        $mime = finfo_file($info, $fullPath) ?: $mime;
        finfo_close($info);
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Cache-Control: private, must-revalidate');
header('Pragma: public');

readfile($fullPath);
exit;
