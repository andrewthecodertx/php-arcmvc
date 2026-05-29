<?php

declare(strict_types=1);

// Application configuration.
// Values are pulled from environment variables (loaded via Arc\Config\EnvLoader)
// with sensible defaults for local development.
return [
    // Application name, used in error pages and logs
    'name' => $_ENV['APP_NAME'] ?? 'Arc',

    // Environment: local, development, staging, production
    // Affects error display, debug mode, and security header strictness
    'env' => $_ENV['APP_ENV'] ?? 'production',

    // Show detailed error pages with stack traces. MUST be false in production.
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),

    // Base URL of the application, used for URL generation and redirects
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',

    // Timezone for date/time functions. Read by Application::loadConfig()
    // but not currently used by the framework core. Set this if your app
    // needs consistent timezone handling.
    'timezone' => 'UTC',

    // Path to view templates (relative to basePath). Used by the Renderer.
    'views_path' => null, // defaults to {basePath}/resources/views
];