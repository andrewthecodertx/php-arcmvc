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
 *
 * By default requests are keyed on the client IP (REMOTE_ADDR). Behind a
 * reverse proxy, pass $trustedProxies so X-Forwarded-For is honored only from
 * those peers. For stricter per-route limits (e.g. /login), supply a
 * $keyResolver that derives the bucket key from the request.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private RateLimitStoreInterface $store;

    /** @var array<int, string> */
    private array $trustedProxies;

    /** @var (\Closure(Request): string)|null */
    private ?\Closure $keyResolver;

    /**
     * @param array<int, string>            $trustedProxies Proxy IPs allowed to set X-Forwarded-For
     * @param (\Closure(Request): string)|null $keyResolver  Custom bucket key resolver
     */
    public function __construct(
        int $maxRequests = 60,
        int $windowSeconds = 60,
        ?RateLimitStoreInterface $store = null,
        array $trustedProxies = [],
        ?\Closure $keyResolver = null,
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->store = $store ?? new InMemoryRateLimitStore();
        $this->trustedProxies = $trustedProxies;
        $this->keyResolver = $keyResolver;
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
        if ($this->keyResolver !== null) {
            return ($this->keyResolver)($request);
        }

        $ip = $request->ip($this->trustedProxies) ?? 'unknown';
        return 'rate_limit:' . $ip;
    }
}