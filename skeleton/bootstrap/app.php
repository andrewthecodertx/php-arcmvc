<?php

declare(strict_types=1);

use Arc\Application;
use Arc\Http\Middleware\SecurityMiddleware;

require __DIR__ . '/../routes/web.php';

$app = new Application(__DIR__ . '/../config');
$app->addMiddleware(SecurityMiddleware::class);

return $app;