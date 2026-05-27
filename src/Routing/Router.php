<?php

declare(strict_types=1);

namespace Arc\Routing;

use Arc\Http\Request;
use Arc\Http\Response;

class Router
{
    private array $routes = [];

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
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];

        $previousRoutes = $this->routes;

        $callback($this);

        foreach ($this->routes as $method => &$methodRoutes) {
            foreach ($methodRoutes as $i => &$route) {
                $wasExisting = false;
                foreach ($previousRoutes[$method] ?? [] as $existing) {
                    if ($existing['pattern'] === $route['pattern']) {
                        $wasExisting = true;
                        break;
                    }
                }
                if (!$wasExisting && $prefix) {
                    $route['pattern'] = '/' . trim($prefix, '/') . $route['pattern'];
                }
                if (!$wasExisting && !empty($middleware)) {
                    $route['middleware'] = array_merge($route['middleware'] ?? [], (array) $middleware);
                }
            }
        }
    }

    private function addRoute(string $method, string $path, callable|array $callback): self
    {
        $pattern = $this->compilePattern($path);
        $this->routes[$method][$pattern] = [
            'pattern' => $pattern,
            'callback' => $callback,
            'path' => $path,
            'middleware' => [],
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

                $response = $this->executeCallback($route['callback'], $request, $params);

                if (!$response instanceof Response) {
                    if (is_array($response)) {
                        $r = new Response();
                        $r->json($response);
                        return $r;
                    }
                    $response = new Response((string) $response);
                }

                return $response;
            }
        }

        if ($method !== 'GET' && isset($this->routes['GET'])) {
            foreach ($this->routes['GET'] as $pattern => $route) {
                if (preg_match("#^{$pattern}$#", $path)) {
                    return new Response('', 405);
                }
            }
        }

        return new Response('Not Found', 404);
    }

    private function executeCallback(callable|array $callback, Request $request, array $params): mixed
    {
        if (is_callable($callback)) {
            return $callback($request, ...array_values($params));
        }

        [$controllerClass, $method] = $callback;
        $controller = new $controllerClass();

        return $controller->$method($request, ...array_values($params));
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}