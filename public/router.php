<?php

declare(strict_types=1);

/**
 * Dev router for PHP built-in server.
 *  
 * Usage:
 *   php -S 127.0.0.1:9999 -t public public/router.php
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uriPath;

// Serve existing files directly (css/js/images/fonts/etc.)
if ($uriPath !== '/' && is_file($file)) {
    return false;
}

// Route everything else (including fake extensions like /admin/settings.sds) to front controller
require __DIR__ . '/index.php';
