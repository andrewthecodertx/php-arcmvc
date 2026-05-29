# Arc

A lightweight, modern PHP MVC framework. Small core, batteries included, built for PHP 8.4+.

## Principles

- No hidden magic. Request flow is traceable from `public/index.php` to response.
- Decoupled core: uses a dedicated DI container for service management.
- Secure defaults: CSRF protection, XSS escaping, SQL injection prevention, security headers.
- Fast startup, low memory by default.
- One canonical way to do common things.
- First-party modules for the common website stack, each optional and independently replaceable.

## Requirements

- PHP 8.4+
- PDO extension (for database features)
- Composer

## Quick Start

```bash
composer require andrewthecoder/arcmvc
cp -r vendor/andrewthecoder/arcmvc/skeleton/* .
arc serve
```

Visit [http://localhost:8080](http://localhost:8080)

## Environment Setup

Copy `.env.example` to `.env` and adjust values:

```bash
cp .env.example .env
```

Arc includes a built-in `.env` loader. Load it early in your bootstrap:

```php
use Arc\Config\EnvLoader;
EnvLoader::load(__DIR__ . '/../.env');
```

Environment variables are available via `$_ENV` and `getenv()`. Existing env vars are never overwritten.

## Routing

Define routes in `routes/web.php`:

```php
$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
```

Route groups with prefix and middleware:

```php
$router->group(['prefix' => 'admin', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->get('/dashboard', [AdminController::class, 'index']);
});
```

## Controllers

Controllers extend `Arc\Support\Controller` and receive the current request via `setRequest()`:

```php
use Arc\Support\Controller;
use Arc\Http\Request;
use Arc\Http\Response;

class UserController extends Controller
{
    public function show(Request $request, string $id): Response
    {
        $user = User::find($id);
        return $this->view('users.show', ['user' => $user]);
    }

    public function store(Request $request): Response
    {
        return $this->redirect('/users');
    }

    protected function back(): Response // Safe redirect, rejects external URLs
    {
        return parent::back();
    }
}
```

Controllers are resolved through the DI container, enabling constructor injection.

## Views and Templates

Views live in `resources/views/` and use `.phtml` files:

```php
<?php $this->extend('main') ?>
<?php $this->section('title', 'Home Page') ?>
<p>Page content here</p>
```

Layouts use `yield()` for content:

```php
<title><?= $this->yield('title', 'Arc') ?></title>
<?= $this->yield('content') ?>
```

### XSS Escaping

Use `e()` to escape user-supplied data:

```php
<h1><?= $this->e($userInput) ?></h1>
```

Content and named sections yielded via `yield()` are **raw** by design (they contain trusted template HTML). Always escape user data with `e()`.

### CSRF Protection

Include a CSRF token in forms:

```php
<form method="POST" action="/users">
    <?= $this->csrfField() ?>
    <input name="name" />
    <button>Save</button>
</form>
```

The `CsrfMiddleware` validates the token automatically on POST, PUT, PATCH, and DELETE requests.

### Partials

```php
<?= $this->partial('shared._nav', ['label' => 'Home']) ?>
```

## Middleware

Register global middleware in your bootstrap:

```php
use Arc\Http\Middleware\SecurityMiddleware;
use Arc\Http\Middleware\CsrfMiddleware;
use Arc\Http\Middleware\RateLimitMiddleware;

$app->addMiddleware(SecurityMiddleware::class);
$app->addMiddleware(new CsrfMiddleware());
$app->addMiddleware(new RateLimitMiddleware(maxRequests: 60, windowSeconds: 60));
```

### Security Headers

`SecurityMiddleware` sets headers with configurable defaults:

```php
new SecurityMiddleware(
    csp: "default-src 'self'; script-src 'self' cdn.example.com",
    hsts: 'max-age=63072000; includeSubDomains; preload'
);
```

### CSRF

`CsrfMiddleware` uses the synchronizer token pattern with a SameSite=Strict HttpOnly cookie. Token is attached to the request as `_csrf_token` attribute, accessible in controllers.

### Rate Limiting

`RateLimitMiddleware` tracks requests per IP with configurable limits:

```php
new RateLimitMiddleware(maxRequests: 100, windowSeconds: 60);
```

For multi-process deployments, implement `RateLimitStoreInterface` with Redis or a database backend.

## Database and Models

Configure the database connection in `config/database.php`, then extend the Model:

```php
use Arc\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email'];
}
```

Available methods:

```php
User::all(limit: 50, offset: 0);               // paginated retrieval
User::find($id);                                 // single record
User::where('email', 'user@example.com');        // conditional query
User::create(['name' => 'Arc', 'email' => '...']);
User::update($id, ['name' => 'Updated']);
User::delete($id);
User::count();                                   // total count
```

Column names in `where()`, `create()`, and `update()` are validated against a strict regex (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`) to prevent SQL injection. Invalid identifiers throw `InvalidArgumentException`.

## Validation

```php
use Arc\Validation\Validator;

$validator = Validator::make($_POST, [
    'name'  => 'required|string|min:2',
    'email' => 'required|email',
    'age'   => 'integer|min:18',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
$validated = $validator->validated();
```

Available rules: `required`, `string`, `integer`, `numeric`, `email`, `url`, `boolean`, `min`, `max`, `between`, `same`, `different`, `in`, `not_in`, `alpha`, `alpha_num`, `regex`, `date`.

The `regex` rule uses `~` as delimiter (supports patterns containing `/`):

```php
'code' => 'regex:^[a-z]+\d+$'
```

Custom error messages:

```php
Validator::make($data, $rules, [
    'email.required' => 'Please enter your email',
]);
```

## Session

```php
use Arc\Http\Session;

$session = new Session();
$session->start();
$session->set('user_id', 42);
$session->get('user_id');            // 42
$session->has('user_id');            // true

// Flash messages (persist for one request)
$session->setFlash('status', 'Saved successfully!');
$session->flash('status');           // 'Saved successfully!' (then cleared)

$session->regenerate();              // prevent session fixation
$session->destroy();                 // end session
```

## File Uploads

```php
// Simple access
$file = $request->getFile('avatar');

// Validated access (checks size, MIME type, upload validity)
$file = $request->validateFile('avatar', maxBytes: 5_242_880, allowedMimes: ['image/jpeg', 'image/png']);
// Throws InvalidArgumentException on failure
```

## Configuration

Config files live in `config/` and return arrays:

```php
// config/app.php
return [
    'name'     => $_ENV['APP_NAME'] ?? 'Arc',
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'url'      => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => 'UTC',
];
```

Access via the application:

```php
$app->config()->get('app.name');      // 'Arc'
$app->config()->get('app.debug');     // false
$app->config()->set('app.theme', 'dark');
```

## DI Container

The container supports explicit bindings, singletons, and auto-wiring:

```php
// Explicit binding
$app->bind(PaymentGateway::class, StripeGateway::class);

// Singleton (resolved once)
$app->singleton(Logger::class, FileLogger::class);

// Auto-wiring (resolves constructor dependencies)
$controller = $app->make(UserController::class);
```

Constructor parameters with class types are resolved from the container. Scalar parameters require defaults or explicit bindings.

## Error Handling

In production (`APP_DEBUG=false`), errors are logged and a generic error page is shown. In debug mode, full stack traces are displayed.

Database errors are wrapped in `DatabaseException` to prevent sensitive SQL and table names from leaking.

## CORS

`CorsMiddleware` handles cross-origin requests and preflight:

```php
use Arc\Http\Middleware\CorsMiddleware;

// Allow specific origins
$app->addMiddleware(new CorsMiddleware(allowedOrigins: ['https://app.example.com']));

// Allow all origins (for APIs)
$app->addMiddleware(new CorsMiddleware(allowedOrigins: '*'));

// With credentials and custom headers
$app->addMiddleware(new CorsMiddleware(
    allowedOrigins: ['https://app.example.com'],
    allowCredentials: true,
    allowedHeaders: ['Content-Type', 'Authorization', 'X-CSRF-TOKEN'],
));
```

## HTTP Method Override

Browser forms only support GET and POST. Arc supports method spoofing via a hidden `_method` field or the `X-HTTP-Method-Override` header:

```html
<form method="POST" action="/users/1">
    <?= $this->csrfField() ?>
    <input type="hidden" name="_method" value="PUT">
    <input name="name" value="Updated">
    <button>Update</button>
</form>
```

Or via API header:

```
X-HTTP-Method-Override: PATCH
```

Only POST requests can be overridden to PUT, PATCH, or DELETE. Use `getOriginalMethod()` to see the actual HTTP method.

## Console Commands

```bash
arc serve              # Start development server (port 8080)
arc serve --port 3000  # Custom port
arc serve:stop         # Stop the development server
arc route:list         # List registered routes
arc make:controller UserController
arc make:model User
```

## License

MIT, see [LICENSE](LICENSE).

## Contributing

PRs welcome. Please open an issue first for major changes. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.