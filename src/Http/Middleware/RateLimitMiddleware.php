<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

/**
 * Rate limiting middleware using a sliding window counter.
 *
 * Tracks request counts per IP address and returns 429 Too Many Requests
 * when the limit is exceeded within the time window.
 *
 * Uses an in-memory store by default. For multi-process or distributed
 * deployments, inject a StoreInterface implementation backed by Redis or a database.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private RateLimitStoreInterface $store;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60, ?RateLimitStoreInterface $store = null)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->store = $store ?? new InMemoryRateLimitStore();
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);
        $hits = $this->store->increment($key, $this->windowSeconds);

        if ($hits > $this->maxRequests) {
            return new Response('Too Many Requests', 429, [
                'Content-Type' => 'text/html',
                'Retry-After' => (string) $this->windowSeconds,
            ]);
        }

        $response = $next($request);

        $remaining = max(0, $this->maxRequests - $hits);
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    private function resolveKey(Request $request): string
    {
        $ip = $request->ip() ?? 'unknown';
        return 'rate_limit:' . $ip;
    }
}