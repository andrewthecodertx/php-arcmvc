<?php

declare(strict_types=1);

namespace Arc\Routing;

use Arc\Container\Container;
use Arc\Http\Request;
use Arc\Http\Response;

class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private ?Container $container = null;

    /**
     * Set the DI container for controller resolution.
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    public function get(string $path, callable|array $callback): self
    {
        return $this->addRoute('GET', $path, $callback);
    }

    public function post(string $path, callable|array $callback): self
    {
        return $this->addRoute('POST', $path, $callback);
    }

    public function put(string $path, callable|array $callback): self
    {
        return $this->addRoute('PUT', $path, $callback);
    }

    public function patch(string $path, callable|array $callback): self
    {
        return $this->addRoute('PATCH', $path, $callback);
    }

    public function delete(string $path, callable|array $callback): self
    {
        return $this->addRoute('DELETE', $path, $callback);
    }

    public function any(string $path, callable|array $callback): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $callback);
        }
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;

        $callback($this);

        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $path, callable|array $callback): self
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $groupAttrs) {
            $groupPrefix = trim($groupAttrs['prefix'] ?? '', '/');
            if ($groupPrefix !== '') {
                $prefix .= '/' . $groupPrefix;
            }
            $middleware = array_merge($middleware, (array) ($groupAttrs['middleware'] ?? []));
        }

        if ($prefix !== '') {
            $path = $prefix . '/' . ltrim($path, '/');
        }

        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/') ?: '/';

        $pattern = $this->compilePattern($path);

        $this->routes[$method][$pattern] = [
            'pattern' => $pattern,
            'callback' => $callback,
            'path' => $path,
            'middleware' => $middleware,
        ];
        return $this;
    }

    private function compilePattern(string $path): string
    {
        return preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if (strtolower($method) === 'head') {
            $method = 'GET';
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $pattern => $route) {
            if (preg_match("#^{$pattern}$#", $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (!empty($route['middleware'])) {
                    return $this->runRouteMiddleware($route['middleware'], $request, $route, $params);
                }

                $response = $this->executeCallback($route['callback'], $request, $params);

                return $this->normalizeResponse($response);
            }
        }

        if ($method !== 'GET' && isset($this->routes['GET'])) {
            foreach ($this->routes['GET'] as $pattern => $route) {
                if (preg_match("#^{$pattern}$#", $path)) {
                    return new Response('', 405);
                }
            }
        }

        throw new RouteNotFoundException($path);
    }

    private function runRouteMiddleware(array $middleware, Request $request, array $route, array $params): Response
    {
        $pipeline = fn (Request $req): Response => $this->normalizeResponse(
            $this->executeCallback($route['callback'], $req, $params)
        );

        foreach (array_reverse($middleware) as $mw) {
            if (is_string($mw)) {
                $mw = $this->container ? $this->container->get($mw) : new $mw();
            }
            $pipeline = fn (Request $req) => $mw->handle($req, $pipeline);
        }

        return $pipeline($request);
    }

    private function executeCallback(callable|array $callback, Request $request, array $params): mixed
    {
        if (is_callable($callback)) {
            return $callback($request, ...array_values($params));
        }

        [$controllerClass, $method] = $callback;

        // Resolve controller through the container when available (supports DI)
        if ($this->container !== null && $this->container->has($controllerClass)) {
            $controller = $this->container->get($controllerClass);
        } else {
            $controller = new $controllerClass();
        }

        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($request);
        }

        return $controller->$method($request, ...array_values($params));
    }

    private function normalizeResponse(mixed $response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response)) {
            $r = new Response();
            $r->json($response);
            return $r;
        }

        return new Response((string) $response);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}