<?php

declare(strict_types=1);

use Arc\Application;
use Arc\Config\EnvLoader;
use Arc\Http\Middleware\CsrfMiddleware;
use Arc\Http\Middleware\SecurityMiddleware;

// Load environment variables from .env
EnvLoader::load(dirname(__DIR__) . '/.env');

$app = new Application(__DIR__ . '/../config', dirname(__DIR__));
$app->addMiddleware(SecurityMiddleware::class);
$app->addMiddleware(new CsrfMiddleware());

$router = $app->router();
require __DIR__ . '/../routes/web.php';

return $app;