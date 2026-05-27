<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Arc\Application;
use Arc\Config\Repository;
use Arc\Http\Request;
use Arc\Http\Response;

class ApplicationTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/arc_test_app_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . '/app.php', '<?php return ["name" => "TestApp", "debug" => false];');
        file_put_contents($this->tmpDir . '/database.php', '<?php return ["default" => "sqlite", "connections" => ["sqlite" => ["driver" => "sqlite", "database" => ":memory:"]]];');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/app.php');
        @unlink($this->tmpDir . '/database.php');
        @rmdir($this->tmpDir);
        Application::getInstance();
    }

    public function testLoadConfig(): void
    {
        $app = new Application($this->tmpDir);
        $this->assertSame('TestApp', $app->config()->get('app.name'));
    }

    public function testRouterDispatch(): void
    {
        $app = new Application();
        $app->router()->get('/test', fn (Request $req) => 'Hello');
        $response = $app->handle(new Request(method: 'GET', uri: '/test'));
        $this->assertSame('Hello', $response->getContent());
    }

    public function testMiddlewarePipeline(): void
    {
        $middleware = new class implements \Arc\Http\MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Custom', 'applied');
                return $response;
            }
        };

        $app = new Application();
        $app->router()->get('/mw', fn (Request $req) => new Response('OK'));
        $app->addMiddleware($middleware);

        $response = $app->handle(new Request(method: 'GET', uri: '/mw'));
        $this->assertSame('applied', $response->getHeaders()['X-Custom'] ?? null);
    }

    public function testExceptionsCaught(): void
    {
        $app = new Application();
        $app->setExceptionHandler(new \Arc\Exceptions\Handler(debug: true));
        $app->router()->get('/boom', fn (Request $req) => throw new \RuntimeException('kaboom'));
        $response = $app->handle(new Request(method: 'GET', uri: '/boom'));
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testBindAndMake(): void
    {
        $app = new Application();
        $app->bind('test.service', fn () => 'hello');
        $this->assertSame('hello', $app->make('test.service'));
    }

    public function testSingletonResolvesOnce(): void
    {
        $app = new Application();
        $counter = 0;
        $app->singleton('counter', function () use (&$counter) {
            $counter++;
            return $counter;
        });

        $app->make('counter');
        $app->make('counter');
        $this->assertSame(1, $counter);
    }

    public function testBootApplication(): void
    {
        $app = new Application($this->tmpDir);
        $this->assertFalse($app->isBooted());
        $app->boot();
        $this->assertTrue($app->isBooted());
    }
}