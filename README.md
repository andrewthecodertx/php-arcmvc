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
composer create-project andrewthecoder/arc myapp
```

Or add to an existing project:

```bash
composer require andrewthecoder/arc
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
│       │   └── main.php
│       └── home/
│           └── index.php
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
use Arc\Routing\Router;
use App\Controllers\HomeController;

require __DIR__ . '/../routes/web.php';

$app = new Application(__DIR__ . '/../config');
$app->addMiddleware(SecurityMiddleware::class);

return $app;
```

### 2. Define Routes

`routes/web.php`:

```php
<?php

declare(strict_types=1);

use Arc\Routing\Router;
use App\Controllers\HomeController;
use App\Controllers\UserController;

Router::get('/', [HomeController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
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

`resources/views/home/index.php`:

```php
<h1><?= $title ?></h1>
<p>Welcome to Arc.</p>
```

`resources/views/layouts/main.php`:

```php
<!DOCTYPE html>
<html>
<head><title>Arc</title></head>
<body>
<?= $content ?>
</body>
</html>
```

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
            'database' => $_ENV['DB_DATABASE'] ?? 'arc',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../database/arc.sqlite',
        ],
    ],
];
```

### Query Builder

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
    // handle errors
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

Register globally in `bootstrap/app.php`:

```php
$app->addMiddleware(AuthMiddleware::class);
```

## Views

Views use plain PHP. The renderer supports dot notation for paths and optional layout wrapping.

```php
// In a controller
return $this->view('users.profile', ['user' => $user]);

// Render a partial inside a view
<?= $this->partial('users._avatar', ['user' => $user']) ?>
```

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