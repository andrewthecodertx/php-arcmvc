<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Arc',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => 'UTC',
];