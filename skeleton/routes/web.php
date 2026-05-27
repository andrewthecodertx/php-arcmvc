<?php

declare(strict_types=1);

use Arc\Routing\Router;
use App\Controllers\HomeController;

Router::get('/', [HomeController::class, 'index']);