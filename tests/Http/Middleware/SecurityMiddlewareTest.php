<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Arc\Http\Middleware\SecurityMiddleware;
use Arc\Http\Request;
use Arc\Http\Response;

class SecurityMiddlewareTest extends TestCase
{
    private SecurityMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new SecurityMiddleware();
    }

    private function createRequest(): Request
    {
        return new Request(method: 'GET', uri: '/');
    }

    public function testSetsXContentTypeOptions(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('nosniff', $response->getHeaders()['X-Content-Type-Options']);
    }

    public function testSetsXFrameOptions(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('DENY', $response->getHeaders()['X-Frame-Options']);
    }

    public function testSetsXXSSProtection(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('0', $response->getHeaders()['X-XSS-Protection']);
    }

    public function testSetsReferrerPolicy(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaders()['Referrer-Policy']);
    }

    public function testSetsDefaultContentSecurityPolicy(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame("default-src 'self'", $response->getHeaders()['Content-Security-Policy']);
    }

    public function testSetsDefaultStrictTransportSecurity(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaders()['Strict-Transport-Security']);
    }

    public function testCustomCspHeader(): void
    {
        $middleware = new SecurityMiddleware(
            csp: "default-src 'self'; script-src 'self' cdn.example.com",
        );
        $next = fn (Request $req): Response => new Response('OK');
        $response = $middleware->handle($this->createRequest(), $next);
        $this->assertSame("default-src 'self'; script-src 'self' cdn.example.com", $response->getHeaders()['Content-Security-Policy']);
    }

    public function testCustomHstsHeader(): void
    {
        $middleware = new SecurityMiddleware(
            hsts: 'max-age=63072000; includeSubDomains; preload',
        );
        $next = fn (Request $req): Response => new Response('OK');
        $response = $middleware->handle($this->createRequest(), $next);
        $this->assertSame('max-age=63072000; includeSubDomains; preload', $response->getHeaders()['Strict-Transport-Security']);
    }

    public function testPassesThroughToNextMiddleware(): void
    {
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $this->middleware->handle($this->createRequest(), $next);
        $this->assertTrue($called);
    }

    public function testPreservesOriginalResponseContent(): void
    {
        $next = fn (Request $req): Response => new Response('Original content');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $this->assertSame('Original content', $response->getContent());
    }

    public function testAllSecurityHeadersPresent(): void
    {
        $next = fn (Request $req): Response => new Response('OK');
        $response = $this->middleware->handle($this->createRequest(), $next);
        $headers = $response->getHeaders();

        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
    }
}