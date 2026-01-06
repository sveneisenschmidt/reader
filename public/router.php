<?php

// Only apply caching in prod mode with PHP built-in server
$isProd = ($_SERVER["APP_ENV"] ?? "dev") === "prod";
$isBuiltInServer = php_sapi_name() === "cli-server";

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Prevent path traversal
$path = "/" . ltrim($path, "/");
$realBase = realpath(__DIR__);
$file = realpath(__DIR__ . $path);

// Ensure file is within public directory
if ($file === false || !str_starts_with($file, $realBase)) {
    require __DIR__ . "/index.php";
    return;
}

if (is_file($file)) {
    if ($isProd && $isBuiltInServer) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $mimeTypes = [
            "css" => "text/css",
            "js" => "application/javascript",
            "woff" => "font/woff",
            "woff2" => "font/woff2",
            "ttf" => "font/ttf",
            "png" => "image/png",
            "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "gif" => "image/gif",
            "svg" => "image/svg+xml",
            "ico" => "image/x-icon",
        ];

        if (isset($mimeTypes[$ext])) {
            header("Content-Type: " . $mimeTypes[$ext]);
            header("Cache-Control: public, max-age=31536000, immutable");
            header("Content-Length: " . filesize($file));
            readfile($file);
            exit();
        }
    }

    return false;
}

require __DIR__ . "/index.php";
