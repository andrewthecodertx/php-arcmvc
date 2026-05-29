<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Arc\Http\Middleware\CorsMiddleware;
use Arc\Http\Request;
use Arc\Http\Response;

class CorsMiddlewareTest extends TestCase
{
    private function createRequest(
        string $method = 'GET',
        string $origin = 'https://example.com',
    ): Request {
        $headers = $origin ? ['Origin' => $origin] : [];
        return new Request(method: $method, uri: '/api/data', headers: $headers);
    }

    // --- Wildcard origin ---

    public function testWildcardAllowsAnyOrigin(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: '*');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://evil.com'), $next);
        $this->assertSame('*', $response->getHeaders()['Access-Control-Allow-Origin']);
    }

    public function testWildcardAllowsRequestWithoutOrigin(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: '*');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', ''), $next);
        // No Origin header — CORS headers are not added for same-origin requests
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    // --- Specific origin ---

    public function testSpecificOriginAllowed(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://trusted.com'), $next);
        $this->assertSame('https://trusted.com', $response->getHeaders()['Access-Control-Allow-Origin']);
    }

    public function testDisallowedOriginGetsNoCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://evil.com'), $next);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    public function testMultipleOrigins(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com', 'https://admin.app.com']);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://admin.app.com'), $next);
        $this->assertSame('https://admin.app.com', $response->getHeaders()['Access-Control-Allow-Origin']);
    }

    // --- Preflight (OPTIONS) ---

    public function testPreflightReturns204WithCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com']);
        $next = fn (Request $req): Response => new Response('Should not reach');

        $response = $middleware->handle($this->createRequest('OPTIONS', 'https://app.com'), $next);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://app.com', $response->getHeaders()['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $response->getHeaders()['Access-Control-Allow-Methods']);
        $this->assertSame('Content-Type, Authorization, X-CSRF-TOKEN, X-HTTP-Method-Override', $response->getHeaders()['Access-Control-Allow-Headers']);
        $this->assertSame('86400', $response->getHeaders()['Access-Control-Max-Age']);
    }

    public function testPreflightDisallowedOriginReturns204WithoutCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com']);
        $next = fn (Request $req): Response => new Response('Should not reach');

        $response = $middleware->handle($this->createRequest('OPTIONS', 'https://evil.com'), $next);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    public function testPreflightWithCustomMethodsAndHeaders(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: 'https://app.com',
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type'],
            maxAge: 3600,
        );
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('OPTIONS', 'https://app.com'), $next);
        $this->assertSame('GET, POST', $response->getHeaders()['Access-Control-Allow-Methods']);
        $this->assertSame('Content-Type', $response->getHeaders()['Access-Control-Allow-Headers']);
        $this->assertSame('3600', $response->getHeaders()['Access-Control-Max-Age']);
    }

    // --- Credentials ---

    public function testAllowCredentialsAddsHeader(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: ['https://app.com'],
            allowCredentials: true,
        );
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://app.com'), $next);
        $this->assertSame('true', $response->getHeaders()['Access-Control-Allow-Credentials']);
    }

    public function testNoCredentialsByDefault(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com']);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://app.com'), $next);
        // Preflight gets 'false', but normal requests don't get this header unless credentials are enabled
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $response->getHeaders());
    }

    // --- Normal responses ---

    public function testNormalResponsePassesThroughToNext(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com']);
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $middleware->handle($this->createRequest('GET', 'https://app.com'), $next);
        $this->assertTrue($called);
        $this->assertSame('OK', $response->getContent());
    }

    public function testNormalResponsePreservesContent(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: '*');
        $next = fn (Request $req): Response => new Response('Hello World');

        $response = $middleware->handle($this->createRequest('GET', 'https://any.com'), $next);
        $this->assertSame('Hello World', $response->getContent());
    }

    public function testExposeHeadersSetOnNormalResponse(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://app.com']);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $middleware->handle($this->createRequest('GET', 'https://app.com'), $next);
        $this->assertSame('X-RateLimit-Limit, X-RateLimit-Remaining', $response->getHeaders()['Access-Control-Expose-Headers']);
    }
}