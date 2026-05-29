<?php

declare(strict_types=1);

namespace Arc;

use Arc\Http\SapiEmitter;
use Arc\Config\Repository;
use Arc\Database\Connection;
use Arc\Exceptions\Handler;
use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;
use Arc\Routing\Router;
use Arc\View\Renderer;
use Arc\Container\Container;

class Application
{
    private static ?Application $instance = null;
    private Repository $config;
    private Router $router;
    private Handler $exceptionHandler;
    private Container $container;
    private array $middleware = [];
    private bool $booted = false;
    private string $basePath;

    public function __construct(?string $configPath = null, ?string $basePath = null)
    {
        $this->basePath = $basePath ?? $this->detectBasePath();
        $this->config = new Repository();
        $this->container = new Container();
        $this->router = new Router();
        $this->router->setContainer($this->container);
        $this->exceptionHandler = new Handler();

        if ($configPath) {
            $this->loadConfig($configPath);
        }

        $this->registerFrameworkServices();

        static::$instance = $this;
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * Reset the singleton instance. For use in tests to ensure clean state.
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    public function setExceptionHandler(Handler $handler): self
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function loadConfig(string $path): self
    {
        if (!is_dir($path)) {
            return $this;
        }

        foreach (glob($path . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config->set($key, require $file);
        }

        $this->exceptionHandler = new Handler(
            $this->config->get('app.debug', false),
        );

        return $this;
    }

    public function config(): Repository
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function bind(string $abstract, callable|string $concrete): self
    {
        $this->container->bind($abstract, $concrete);
        return $this;
    }

    public function singleton(string $abstract, callable|string $concrete): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    public function make(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }
        $this->middleware[] = $middleware;
        return $this;
    }

    public function setMiddleware(array $middleware): self
    {
        $this->middleware = [];
        foreach ($middleware as $m) {
            $this->addMiddleware($m);
        }
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function handle(Request $request): Response
    {
        try {
            if (!empty($this->middleware)) {
                return $this->runMiddleware($request, $this->middleware);
            }

            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return $this->exceptionHandler->handle($e);
        }
    }

    private function runMiddleware(Request $request, array $middleware): Response
    {
        $pipeline = fn (Request $req): Response => $this->router->dispatch($req);

        foreach (array_reverse($middleware) as $mw) {
            $pipeline = fn (Request $req) => $mw->handle($req, $pipeline);
        }

        return $pipeline($request);
    }

    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        
        $emitter = $this->container->get(SapiEmitter::class);
        $emitter->emit($response);
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->registerDatabaseConnection();
        $this->booted = true;
        return $this;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    private function registerFrameworkServices(): void
    {
        $this->singleton(SapiEmitter::class, fn () => new SapiEmitter());

        $this->singleton(Renderer::class, function (Container $container) {
            $viewsPath = $this->config()->get('app.views_path', $this->basePath('resources/views'));
            return new Renderer($viewsPath);
        });
    }

    private function registerDatabaseConnection(): void
    {
        $dbConfig = $this->config->get(
            'database.connections.' . $this->config->get('database.default', 'mysql')
        );

        if ($dbConfig) {
            $this->singleton(Connection::class, fn () => Connection::make($dbConfig));
        }
    }

    private function detectBasePath(): string
    {
        $root = getcwd();

        if ($root && is_dir($root . '/app') && is_dir($root . '/public')) {
            return $root;
        }

        return dirname(__DIR__);
    }
}