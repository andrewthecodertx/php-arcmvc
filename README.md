# Arc

A lightweight, modern PHP MVC framework. Small core, batteries included, built for PHP 8.4+.

## Principles

- No hidden magic. Request flow is traceable from `public/index.php` to response.
- Every subsystem is replaceable.
- Secure defaults everywhere.
- Fast startup, low memory by default.
- One canonical way to do common things.
- First-party modules for the common website stack, each optional and independently replaceable.

## Requirements

- PHP 8.4+
- PDO extension (for database features)
- Composer

## Install

```bash
composer require andrewthecoder/arc
```

Or create a new project from the skeleton:

```bash
cp -r vendor/andrewthecoder/arc/skeleton/* .
```

## Project Structure

```
myapp/
├── app/
│   ├── Controllers/
│   ├── Models/
│   └── Middleware/
├── config/
│   ├── app.php
│   └── database.php
├── public/
│   └── index.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── main.phtml
│       └── home/
│           └── index.phtml
├── routes/
│   └── web.php
├── bootstrap/
│   └── app.php
└── .env
```

## Getting Started

### 1. Bootstrap the Application

`public/index.php` is the entry point:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();
$app->run();
```

`bootstrap/app.php` wires things together:

```php
<?php

declare(strict_types=1);

use Arc\Application;
use Arc\Http\Middleware\SecurityMiddleware;

$app = new Application(__DIR__ . '/../config', dirname(__DIR__));
$app->addMiddleware(SecurityMiddleware::class);

$router = $app->router();
require __DIR__ . '/../routes/web.php';

return $app;
```

### 2. Define Routes

`routes/web.php` receives the `$router` from the bootstrap:

```php
<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\UserController;

/** @var \Arc\Routing\Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->post('/users', [UserController::class, 'store']);

// Route groups with prefix and middleware
$router->group(['prefix' => '/admin', 'middleware' => App\Middleware\AuthMiddleware::class], function (\Arc\Routing\Router $router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
});
```

### 3. Write a Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Arc\Support\Controller;
use Arc\Http\Request;
use Arc\Http\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home.index', ['title' => 'Welcome']);
    }
}
```

### 4. Write a View

Views use `.phtml` files with `$this` bound to a `Template` object:

`resources/views/home/index.phtml`:

```php
<?php /** @var \Arc\View\Template $this */ ?>
<?php $this->extend('main') ?>
<?php $this->section('title', 'Home') ?>

<h1><?= $title ?></h1>
<p>Welcome to Arc.</p>
```

`resources/views/layouts/main.phtml`:

```php
<?php /** @var \Arc\View\Template $this */ ?>
<!DOCTYPE html>
<html>
<head><title><?= $this->yield('title', 'Arc') ?></title></head>
<body>
<?= $this->yield('content') ?>
</body>
</html>
```

Views that don't call `$this->extend()` render as-is, no layout applied. The `@var` annotation helps IDEs and static analysis understand `$this`.

## Console

Arc includes a CLI tool:

```bash
# Start dev server
arc serve

# With options
arc serve --port=3000 --host=0.0.0.0

# Generate a controller
arc make:controller UserController

# Generate a model
arc make:model User --table=users

# List registered routes
arc routes

# Show help
arc help
```

### Custom Commands

```php
use Arc\Console\Commands\Command;

class MigrateCommand extends Command
{
    public function name(): string { return 'migrate'; }
    public function description(): string { return 'Run database migrations'; }
    public function run(array $args): int
    {
        $this->info('Running migrations...');
        return 0;
    }
}

// In bootstrap/app.php:
use Arc\Console\Kernel;
$console = new Kernel();
$console->register(new MigrateCommand());
```

## Database

Arc uses PDO under the hood. Configure in `config/database.php`:

```php
<?php

declare(strict_types=1);

return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'arc',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../database/arc.sqlite',
        ],
    ],
];
```

### Connection

After `$app->boot()`, the Connection is available via the container:

```php
use Arc\Database\Connection;

$conn = $app->make(Connection::class);

// Select
$users = $conn->select('SELECT * FROM users WHERE active = :active', ['active' => 1]);

// Insert
$id = $conn->insert('INSERT INTO users (name, email) VALUES (:name, :email)', [
    'name' => 'Arc',
    'email' => 'arc@example.com',
]);

// Update
$affected = $conn->update('UPDATE users SET name = :name WHERE id = :id', [
    'name' => 'Updated',
    'id' => 1,
]);

// Transaction
$conn->transaction(function (Connection $c) {
    $c->insert('INSERT INTO orders (user_id) VALUES (:id)', ['id' => 1]);
    $c->update('UPDATE users SET order_count = order_count + 1 WHERE id = :id', ['id' => 1]);
});
```

### Model

```php
use Arc\Database\Model;
use Arc\Database\Connection;

// Wire the connection (typically in bootstrap)
Model::setConnection($app->make(Connection::class));

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email'];
}

// Usage
$all = User::all();
$user = User::find(1);
$id = User::create(['name' => 'Arc', 'email' => 'arc@example.com']);
User::update(1, ['name' => 'Updated']);
User::delete(1);
$count = User::count();
```

## Validation

```php
use Arc\Validation\Validator;

$v = Validator::make($_POST, [
    'name' => 'required|string|min:2',
    'email' => 'required|email',
    'password' => 'required|min:8',
    'password_confirm' => 'required|same:password',
]);

if ($v->fails()) {
    $errors = $v->errors();
}

$validated = $v->validated();
```

Available rules: `required`, `string`, `integer`, `numeric`, `email`, `url`, `boolean`, `min:N`, `max:N`, `between:min,max`, `same:field`, `different:field`, `in:a,b,c`, `not_in:a,b,c`, `alpha`, `alpha_num`, `regex:pattern`, `date`.

Custom messages:

```php
Validator::make($data, $rules, [
    'name.required' => 'Please enter your name.',
    'email.email' => 'That does not look like an email.',
]);
```

## Middleware

Create middleware implementing `MiddlewareInterface`:

```php
use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$this->isLoggedIn()) {
            return new Response('', 401);
        }
        return $next($request);
    }
}
```

Register globally:

```php
$app->addMiddleware(AuthMiddleware::class);
```

Or on a route group:

```php
$router->group(['middleware' => AuthMiddleware::class], function (\Arc\Routing\Router $r) {
    $r->get('/dashboard', [DashboardController::class, 'index']);
});
```

## Views

Views use `.phtml` files with `$this` bound to a `Template` object. The renderer supports dot notation for paths.

```php
// In a controller
return $this->view('users.profile', ['user' => $user]);
```

### Template API

Available in every `.phtml` via `$this`:

| Method | Description |
|--------|-------------|
| `$this->extend('main')` | Wrap this view in `layouts/main.phtml` |
| `$this->section('title', 'Home')` | Define a named section for the layout |
| `$this->yield('title', 'Default')` | Output a section (or default) from the view |
| `$this->yield('content')` | Output the view's rendered content |
| `$this->partial('nav.main', [...])` | Render a sub-template |

### Layouts

A layout is just a `.phtml` file in `resources/views/layouts/`. It uses `yield()` to inject sections from the view:

```php
<?php /** @var \Arc\View\Template $this */ ?>
<html>
<head><title><?= $this->yield('title', 'Arc') ?></title></head>
<body><?= $this->yield('content') ?></body>
</html>
```

A view opts in by calling `extend()`:

```php
<?php /** @var \Arc\View\Template $this */ ?>
<?php $this->extend('main') ?>
<?php $this->section('title', 'Profile') ?>
<h1>Profile</h1>
```

No `extend()` call means the view renders standalone.

Configure the views path in `config/app.php`:

```php
'views_path' => dirname(__DIR__) . '/resources/views',
```

Or let Arc default to `{basePath}/resources/views`.

## Configuration

Config files live in `config/`. Access values with dot notation:

```php
$app->config()->get('app.name');
$app->config()->get('database.default');
$app->config()->set('app.debug', true);
$app->config()->has('app.timezone');
```

## Dependency Container

```php
// Bind a new instance each time
$app->bind(PaymentGateway::class, StripeGateway::class);

// Bind a singleton
$app->singleton(DatabaseConnection::class, fn ($app) => new DatabaseConnection($app->config()));

// Resolve
$gateway = $app->make(PaymentGateway::class);
```

## Testing

```bash
vendor/bin/phpunit
```

## License

MIT