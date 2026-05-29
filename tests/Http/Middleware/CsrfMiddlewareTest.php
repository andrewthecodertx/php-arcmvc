<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Arc\Http\Middleware\CsrfMiddleware;
use Arc\Http\Request;
use Arc\Http\Response;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new CsrfMiddleware();
    }

    private function createRequest(
        string $method = 'GET',
        array $post = [],
        array $cookies = [],
        array $headers = [],
        array $body = [],
    ): Request {
        return new Request(
            method: $method,
            uri: '/test',
            headers: $headers,
            query: [],
            post: $post,
            body: $body,
            cookies: $cookies,
        );
    }

    public function testGetRequestPassesThroughWithoutToken(): void
    {
        $request = $this->createRequest('GET');
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetRequestSetsCsrfCookieWhenMissing(): void
    {
        $request = $this->createRequest('GET');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $header = $response->getHeaders()['Set-Cookie'] ?? null;
        $this->assertNotNull($header);
        $this->assertStringStartsWith('csrf_token=', $header);
        $this->assertStringContainsString('SameSite=Strict', $header);
        $this->assertStringContainsString('HttpOnly', $header);
    }

    public function testGetRequestDoesNotSetCookieWhenAlreadyPresent(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('GET', cookies: ['csrf_token' => $token]);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertArrayNotHasKey('Set-Cookie', $response->getHeaders());
    }

    public function testPostRequestRejectsMissingToken(): void
    {
        $request = $this->createRequest('POST', post: []);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('CSRF token mismatch', $response->getContent());
    }

    public function testPostRequestAcceptsValidTokenInFormData(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('POST', post: ['_token' => $token], cookies: ['csrf_token' => $token]);
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostRequestAcceptsValidTokenInHeader(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('POST', headers: ['X-CSRF-TOKEN' => $token], cookies: ['csrf_token' => $token]);
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostRequestRejectsInvalidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('POST', post: ['_token' => 'wrong_token'], cookies: ['csrf_token' => $token]);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testPostRequestRejectsEmptyToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('POST', post: ['_token' => ''], cookies: ['csrf_token' => $token]);
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testPutRequestRequiresToken(): void
    {
        $request = $this->createRequest('PUT');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testDeleteRequestRequiresToken(): void
    {
        $request = $this->createRequest('DELETE');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testPatchRequestRequiresToken(): void
    {
        $request = $this->createRequest('PATCH');
        $next = fn (Request $req): Response => new Response('OK');

        $response = $this->middleware->handle($request, $next);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testHeadRequestDoesNotRequireToken(): void
    {
        $request = $this->createRequest('HEAD');
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOptionsRequestDoesNotRequireToken(): void
    {
        $request = $this->createRequest('OPTIONS');
        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testTokenIsAttachedToRequest(): void
    {
        $request = $this->createRequest('GET');
        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest): Response {
            $capturedRequest = $req;
            return new Response('OK');
        };

        $this->middleware->handle($request, $next);
        $token = $capturedRequest->getAttribute(CsrfMiddleware::TOKEN_ATTR);
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testTokenPersistedFromCookie(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('GET', cookies: ['csrf_token' => $token]);
        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest): Response {
            $capturedRequest = $req;
            return new Response('OK');
        };

        $this->middleware->handle($request, $next);
        $retrieved = $capturedRequest->getAttribute(CsrfMiddleware::TOKEN_ATTR);
        $this->assertSame($token, $retrieved);
    }

    public function testInvalidCookieFormatGeneratesNewToken(): void
    {
        $request = $this->createRequest('GET', cookies: ['csrf_token' => 'not-a-valid-token']);
        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest): Response {
            $capturedRequest = $req;
            return new Response('OK');
        };

        $this->middleware->handle($request, $next);
        $token = $capturedRequest->getAttribute(CsrfMiddleware::TOKEN_ATTR);
        // Should be a valid 64-char hex string, not the invalid cookie
        $this->assertSame(64, strlen($token));
        $this->assertNotSame('not-a-valid-token', $token);
    }

    public function testFieldStaticMethodGeneratesHiddenInput(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('GET');
        $request->setAttribute(CsrfMiddleware::TOKEN_ATTR, $token);

        $html = CsrfMiddleware::field($request);
        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '"', $html);
    }

    public function testFieldStaticMethodUsesCustomFieldName(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('GET');
        $request->setAttribute(CsrfMiddleware::TOKEN_ATTR, $token);

        $html = CsrfMiddleware::field($request, 'csrf');
        $this->assertStringContainsString('name="csrf"', $html);
    }

    public function testTokenStaticMethodRetrievesFromRequest(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest('GET');
        $request->setAttribute(CsrfMiddleware::TOKEN_ATTR, $token);

        $this->assertSame($token, CsrfMiddleware::token($request));
    }

    public function testJsonBodyTokenAccepted(): void
    {
        $token = bin2hex(random_bytes(32));
        $request = $this->createRequest(
            'POST',
            post: [],
            cookies: ['csrf_token' => $token],
            headers: ['Content-Type' => 'application/json'],
            body: ['_token' => $token],
        );

        $called = false;
        $next = function (Request $req) use (&$called): Response {
            $called = true;
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }
}