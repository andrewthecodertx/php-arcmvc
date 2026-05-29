<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use Arc\Routing\Router;
use Arc\Http\Request;
use Arc\Http\Response;
use Arc\Routing\RouteNotFoundException;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testGetRoute(): void
    {
        $this->router->get('/hello/{name}', fn (Request $req, string $name) => new Response("Hello, {$name}!"));
        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/hello/Arc'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello, Arc!', $response->getContent());
    }

    public function testPostRoute(): void
    {
        $this->router->post('/submit', fn (Request $req) => new Response('Submitted', 201));
        $response = $this->router->dispatch(new Request(method: 'POST', uri: '/submit'));
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testPutRoute(): void
    {
        $this->router->put('/update/{id}', fn (Request $req, string $id) => new Response("Updated {$id}"));
        $response = $this->router->dispatch(new Request(method: 'PUT', uri: '/update/5'));
        $this->assertSame('Updated 5', $response->getContent());
    }

    public function testPatchRoute(): void
    {
        $this->router->patch('/patch/{id}', fn (Request $req, string $id) => new Response("Patched {$id}"));
        $response = $this->router->dispatch(new Request(method: 'PATCH', uri: '/patch/5'));
        $this->assertSame('Patched 5', $response->getContent());
    }

    public function testDeleteRoute(): void
    {
        $this->router->delete('/delete/{id}', fn (Request $req, string $id) => new Response("Deleted {$id}"));
        $response = $this->router->dispatch(new Request(method: 'DELETE', uri: '/delete/5'));
        $this->assertSame('Deleted 5', $response->getContent());
    }

    public function testNotFoundThrowsRouteNotFoundException(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch(new Request(method: 'GET', uri: '/nonexistent'));
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $this->router->get('/resource', fn (Request $req) => new Response('OK'));
        $response = $this->router->dispatch(new Request(method: 'POST', uri: '/resource'));
        $this->assertSame(405, $response->getStatusCode());
    }

    public function testClosureReturnsStringAutoWrapped(): void
    {
        $this->router->get('/hello', fn (Request $req) => 'Hello World');
        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/hello'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello World', $response->getContent());
    }

    public function testClosureReturnsArrayAutoJson(): void
    {
        $this->router->get('/api', fn (Request $req) => ['status' => 'ok']);
        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/api'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testControllerCallback(): void
    {
        $this->router->get('/home', [TestController::class, 'index']);
        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/home'));
        $this->assertSame('Home page', $response->getContent());
    }

    public function testControllerWithParams(): void
    {
        $this->router->get('/user/{id}', [TestController::class, 'show']);
        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/user/42'));
        $this->assertSame('User 42', $response->getContent());
    }

    public function testAnyMatchesAllMethods(): void
    {
        $this->router->any('/catch-all', fn (Request $req) => 'caught');

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->router->dispatch(new Request(method: $method, uri: '/catch-all'));
            $this->assertSame('caught', $response->getContent());
        }
    }

    public function testGetRoutesReturnsRegistered(): void
    {
        $this->router->get('/a', fn (Request $req) => 'a');
        $this->router->post('/b', fn (Request $req) => 'b');

        $routes = $this->router->getRoutes();
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
    }

    public function testGroupAddsPrefix(): void
    {
        $this->router->group(['prefix' => '/admin'], function (Router $r) {
            $r->get('/dashboard', fn (Request $req) => 'admin dashboard');
        });

        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/admin/dashboard'));
        $this->assertSame('admin dashboard', $response->getContent());
    }

    public function testNestedGroups(): void
    {
        $this->router->group(['prefix' => '/admin'], function (Router $r) {
            $r->group(['prefix' => '/users'], function (Router $r) {
                $r->get('/list', fn (Request $req) => 'user list');
            });
        });

        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/admin/users/list'));
        $this->assertSame('user list', $response->getContent());
    }

    public function testGroupMiddlewareApplied(): void
    {
        $this->router->group(['middleware' => TestMiddleware::class], function (Router $r) {
            $r->get('/protected', fn (Request $req) => new Response('secret'));
        });

        $response = $this->router->dispatch(new Request(method: 'GET', uri: '/protected'));
        $this->assertSame('middleware applied', $response->getContent());
    }

    public function testHeadMethodTreatedAsGet(): void
    {
        $this->router->get('/resource', fn (Request $req) => 'resource body');
        $response = $this->router->dispatch(new Request(method: 'HEAD', uri: '/resource'));
        $this->assertSame(200, $response->getStatusCode());
    }
}

class TestController
{
    public function index(Request $request): Response
    {
        return new Response('Home page');
    }

    public function show(Request $request, string $id): Response
    {
        return new Response("User {$id}");
    }
}

class TestMiddleware implements \Arc\Http\MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return new Response('middleware applied');
    }
}