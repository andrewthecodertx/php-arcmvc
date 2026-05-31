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

    /**
     * Compile a route path with {param} placeholders into a regex pattern.
     * e.g., /users/{id} becomes /users/(?P<id>[^/]+)
     */
    private function compilePattern(string $path): string
    {
        return preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
    }

    /**
     * Dispatch a request through the router.
     *
     * 1. Normalize HEAD requests to GET (same response, no body)
     * 2. Match request path against registered route patterns
     * 3. If matched with middleware, run the middleware pipeline
     * 4. If matched without middleware, execute the callback directly
     * 5. If no match, check if the path exists for other methods (405)
     * 6. Otherwise throw RouteNotFoundException (404)
     *
     * @throws RouteNotFoundException if no matching route is found
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // HEAD is served by the matching GET route, but the body is stripped
        // before the response leaves the router (per HTTP semantics).
        $isHead = strtolower($method) === 'head';
        if ($isHead) {
            $method = 'GET';
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $pattern => $route) {
            if (preg_match("#^{$pattern}$#", $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (!empty($route['middleware'])) {
                    $response = $this->runRouteMiddleware($route['middleware'], $request, $route, $params);
                } else {
                    $response = $this->normalizeResponse(
                        $this->executeCallback($route['callback'], $request, $params)
                    );
                }

                return $isHead ? $response->setContent('') : $response;
            }
        }

        // Path exists under other methods → 405 Method Not Allowed with Allow header (RFC 9110).
        $allowed = $this->allowedMethodsFor($path, $method);
        if ($allowed !== []) {
            return new Response('', 405, ['Allow' => implode(', ', $allowed)]);
        }

        throw new RouteNotFoundException($path);
    }

    /**
     * Collect the HTTP methods registered for a path, excluding $except.
     * GET implicitly allows HEAD.
     *
     * @return array<int, string>
     */
    private function allowedMethodsFor(string $path, string $except): array
    {
        $allowed = [];

        foreach ($this->routes as $routeMethod => $routes) {
            if ($routeMethod === $except) {
                continue;
            }

            foreach ($routes as $pattern => $_) {
                if (preg_match("#^{$pattern}$#", $path)) {
                    $allowed[] = $routeMethod;
                    if ($routeMethod === 'GET') {
                        $allowed[] = 'HEAD';
                    }
                    break;
                }
            }
        }

        return array_values(array_unique($allowed));
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

        if ($controller instanceof \Arc\Support\Controller) {
            $controller->setRequest($request);
            if ($this->container !== null) {
                $controller->setRenderer($this->container->get(\Arc\View\Renderer::class));
            }
        } elseif (method_exists($controller, 'setRequest')) {
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