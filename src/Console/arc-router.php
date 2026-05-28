<?php

declare(strict_types=1);

/**
 * Arc development server router script.
 *
 * Used by PHP's built-in server via: php -S host:port arc-router.php
 *
 * Routes all non-static requests through the application front controller.
 * Serves existing static files directly for performance.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve existing static files directly
$publicPath = __DIR__ . $uri;
if ($uri !== '/' && is_file($publicPath)) {
    return false;
}

// All other requests go through the front controller
require __DIR__ . '/index.php';