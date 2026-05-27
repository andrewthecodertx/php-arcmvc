<?php

declare(strict_types=1);

use Arc\Application;
use Arc\Http\Middleware\SecurityMiddleware;
use Arc\Routing\Router;

$app = new Application(__DIR__ . '/../config', dirname(__DIR__));
$app->addMiddleware(SecurityMiddleware::class);

$router = $app->router();
require __DIR__ . '/../routes/web.php';

return $app;