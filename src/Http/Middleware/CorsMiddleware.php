<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

/**
 * CORS middleware for handling cross-origin requests.
 *
 * Handles preflight OPTIONS requests and adds appropriate CORS headers
 * to all responses. Configurable origins, methods, headers, and credentials.
 *
 * Usage:
 *   $cors = new CorsMiddleware(allowedOrigins: ['https://example.com']);
 *   $app->addMiddleware($cors);
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    /**
     * @param array|string $allowedOrigins   Single origin, list of origins, or '*' for all.
     *                                      For production, specify exact origins.
     * @param array        $allowedMethods   HTTP methods allowed (default: GET, POST, PUT, PATCH, DELETE, OPTIONS)
     * @param array        $allowedHeaders  Request headers allowed (default: Content-Type, Authorization, X-CSRF-TOKEN, X-HTTP-Method-Override)
     * @param bool         $allowCredentials Whether to allow cookies/credentials (default: false)
     * @param int          $maxAge          Preflight cache duration in seconds (default: 86400 = 24 hours)
     */
    public function __construct(
        array|string $allowedOrigins = '*',
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-CSRF-TOKEN', 'X-HTTP-Method-Override'],
        bool $allowCredentials = false,
        int $maxAge = 86400,
    ) {
        $this->allowedOrigins = is_array($allowedOrigins) ? $allowedOrigins : [$allowedOrigins];
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
        $this->maxAge = $maxAge;

        // The CORS spec forbids reflecting a wildcard origin together with
        // credentials; browsers reject the response. Fail fast at construction.
        if ($allowCredentials && in_array('*', $this->allowedOrigins, true)) {
            throw new \InvalidArgumentException(
                'CORS misconfiguration: allowedOrigins "*" cannot be combined with allowCredentials. '
                . 'Specify explicit origins when credentials are enabled.'
            );
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle a CORS preflight (OPTIONS) request.
     */
    private function handlePreflight(Request $request): Response
    {
        $origin = $request->getHeader('Origin');

        if (!$this->isOriginAllowed($origin)) {
            return new Response('', 204);
        }

        $headers = [
            'Access-Control-Allow-Origin' => $this->resolveOrigin($origin),
            'Access-Control-Allow-Methods' => implode(', ', $this->allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $this->allowedHeaders),
            'Access-Control-Max-Age' => (string) $this->maxAge,
        ];

        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // When the response varies per-origin, caches must key on Origin.
        if (!$this->isWildcard()) {
            $headers['Vary'] = 'Origin';
        }

        return new Response('', 204, $headers);
    }

    /**
     * Add CORS headers to a normal response.
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->getHeader('Origin');

        if ($origin && $this->isOriginAllowed($origin)) {
            $response->setHeader('Access-Control-Allow-Origin', $this->resolveOrigin($origin));

            if ($this->allowCredentials) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            // When the response varies per-origin, caches must key on Origin.
            if (!$this->isWildcard()) {
                $response->setHeader('Vary', 'Origin');
            }

            // Expose headers that JavaScript clients can read
            $response->setHeader('Access-Control-Expose-Headers', 'X-RateLimit-Limit, X-RateLimit-Remaining');
        }

        return $response;
    }

    /**
     * Check if the request origin is in the allowed list.
     */
    private function isWildcard(): bool
    {
        return in_array('*', $this->allowedOrigins, true);
    }

    private function isOriginAllowed(?string $origin): bool
    {
        if ($origin === null) {
            return false;
        }

        // Wildcard allows all origins
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Resolve the Access-Control-Allow-Origin header value.
     * Returns the specific origin if allowed, or '*' for wildcard.
     */
    private function resolveOrigin(?string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        return $origin ?? '*';
    }
}