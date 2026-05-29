<?php

declare(strict_types=1);

// Database configuration.
// Arc supports MySQL and SQLite out of the box via PDO.
// Connection is lazy-loaded: no connection is made until the first query.
return [
    // Default connection name (must match a key in 'connections')
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',

    'connections' => [
        'mysql' => [
            // PDO driver name
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'arc',
            // Use a dedicated application user, not root
            'username' => $_ENV['DB_USERNAME'] ?? 'app_user',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            // Path to the SQLite database file (:memory: for in-memory)
            'database' => __DIR__ . '/../database/arc.sqlite',
        ],
    ],
];