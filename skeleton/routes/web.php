<?php

declare(strict_types=1);

use App\Controllers\HomeController;

/** @var \Arc\Routing\Router $router */

$router->get('/', [HomeController::class, 'index']);