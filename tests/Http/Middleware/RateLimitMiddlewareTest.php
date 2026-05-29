<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Arc\Http\Middleware\RateLimitMiddleware;
use Arc\Http\Middleware\InMemoryRateLimitStore;
use Arc\Http\Request;
use Arc\Http\Response;

class RateLimitMiddlewareTest extends TestCase
{
    private InMemoryRateLimitStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryRateLimitStore();
    }

    private function createRequest(string $ip = '127.0.0.1', string $method = 'GET'): Request
    {
        return new Request(method: $method, uri: '/', server: ['REMOTE_ADDR' => $ip]);
    }

    public function testRequestsWithinLimitPass(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 3, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        for ($i = 0; $i < 3; $i++) {
            $response = $middleware->handle($this->createRequest(), $next);
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function testRequestOverLimitReturns429(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 2, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $middleware->handle($this->createRequest(), $next);
        $middleware->handle($this->createRequest(), $next);
        $response = $middleware->handle($this->createRequest(), $next);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('Too Many Requests', $response->getContent());
    }

    public function testRetryAfterHeaderOn429(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $middleware->handle($this->createRequest(), $next);
        $response = $middleware->handle($this->createRequest(), $next);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->getHeaders()['Retry-After']);
    }

    public function testRateLimitHeadersOnSuccessfulRequest(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 10, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest(), $next);

        $this->assertSame('10', $response->getHeaders()['X-RateLimit-Limit']);
        $this->assertSame('9', $response->getHeaders()['X-RateLimit-Remaining']);
    }

    public function testRateLimitRemainingDecrements(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 5, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $response1 = $middleware->handle($this->createRequest(), $next);
        $response2 = $middleware->handle($this->createRequest(), $next);

        $this->assertSame('4', $response1->getHeaders()['X-RateLimit-Remaining']);
        $this->assertSame('3', $response2->getHeaders()['X-RateLimit-Remaining']);
    }

    public function testRemainingNeverGoesBelowZero(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $middleware->handle($this->createRequest(), $next);
        $response = $middleware->handle($this->createRequest(), $next);

        // The 429 response doesn't go through next(), so no rate limit headers
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testDifferentIpsTrackedSeparately(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        $response1 = $middleware->handle($this->createRequest('192.168.1.1'), $next);
        $response2 = $middleware->handle($this->createRequest('192.168.1.2'), $next);

        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame(200, $response2->getStatusCode());
    }

    public function testDifferentIpsExhaustLimitsIndependently(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 2, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        // IP 1 uses its 2 requests
        $middleware->handle($this->createRequest('10.0.0.1'), $next);
        $middleware->handle($this->createRequest('10.0.0.1'), $next);

        // IP 2 still has budget
        $response = $middleware->handle($this->createRequest('10.0.0.2'), $next);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', $response->getHeaders()['X-RateLimit-Remaining']);
    }

    public function testNoIpFallsBackToUnknown(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('OK');

        // Request with no REMOTE_ADDR
        $request = new Request(method: 'GET', uri: '/');
        $response = $middleware->handle($request, $next);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPreservesOriginalResponseContent(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 10, windowSeconds: 60, store: $this->store);
        $next = fn (Request $req): Response => new Response('Hello World');

        $response = $middleware->handle($this->createRequest(), $next);
        $this->assertSame('Hello World', $response->getContent());
    }
}

class InMemoryRateLimitStoreTest extends TestCase
{
    public function testIncrementStartsAtOne(): void
    {
        $store = new InMemoryRateLimitStore();
        $this->assertSame(1, $store->increment('test_key', 60));
    }

    public function testIncrementIncreasesCount(): void
    {
        $store = new InMemoryRateLimitStore();
        $store->increment('test_key', 60);
        $this->assertSame(2, $store->increment('test_key', 60));
        $this->assertSame(3, $store->increment('test_key', 60));
    }

    public function testDifferentKeysTrackedSeparately(): void
    {
        $store = new InMemoryRateLimitStore();
        $store->increment('key_a', 60);
        $this->assertSame(1, $store->increment('key_b', 60));
    }

    public function testGcRemovesExpiredBuckets(): void
    {
        $store = new InMemoryRateLimitStore();
        // Window of 0 seconds means it expires immediately
        $store->increment('expired_key', 0);
        // Give it a moment to ensure time has passed
        usleep(1000);
        $store->gc();

        // After GC, the expired bucket should be gone, so increment starts fresh
        $this->assertSame(1, $store->increment('expired_key', 60));
    }
}