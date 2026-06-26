<?php
/**
 * Dev-server router for PHP's built-in web server.
 *
 * Run with:  php -S 127.0.0.1:8000 -t public public/router.php
 *
 * Why this exists: when you run `php -S ... public/index.php`, the built-in
 * server pushes static assets (.js/.css/...) through Symfony and labels them
 * `text/html`. This router serves any existing file under public/ directly,
 * with a correct Content-Type, and only hands real routes to Symfony.
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Serve an existing static file (not the front controller) with a proper MIME.
if ($uri !== '/' && $uri !== '/index.php' && is_file($file)) {
    $mimes = [
        'js'   => 'text/javascript',
        'mjs'  => 'text/javascript',
        'css'  => 'text/css',
        'json' => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'map'  => 'application/json',
        'webm' => 'video/webm',
        'mp4'  => 'video/mp4',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    // Let PHP files (other than index.php) fall through to the front controller.
    if ($ext !== 'php') {
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($file);
        return true;
    }
}

// Everything else → Symfony front controller.
require __DIR__ . '/index.php';
