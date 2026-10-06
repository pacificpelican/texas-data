<?php
// Router for the PHP built-in server: php -S 127.0.0.1:8000 router.php
// Serves real files directly and renders 404.php for anything unknown.

$path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = '/' . ltrim((string) $path, '/');

$file = realpath(__DIR__ . $path);
if ($file !== false && strpos($file, __DIR__) === 0 && is_file($file)) {
    return false; // let the built-in server serve the file as-is
}

if ($file !== false && is_dir($file)) {
    $index = $file . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
