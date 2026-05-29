<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Arc\Support\Controller;
use Arc\Http\Request;
use Arc\Http\Response;

class TestController extends Controller
{
    public function testBack(): Response
    {
        return $this->back();
    }

    public function testIsSafeRedirect(string $url): bool
    {
        return $this->isSafeRedirect($url);
    }

    public function setTestRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }
}

class ControllerTest extends TestCase
{
    private function makeController(array $headers = []): TestController
    {
        $request = new Request(method: 'GET', uri: '/', headers: $headers);
        $controller = new TestController();
        $controller->setTestRequest($request);
        return $controller;
    }

    // --- Open redirect prevention tests ---

    public function testBackWithNoRefererRedirectsToRoot(): void
    {
        $controller = $this->makeController([]);
        $response = $controller->testBack();
        $this->assertSame('/', $response->getHeaders()['Location']);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testBackWithRelativeRefererRedirectsSafely(): void
    {
        $controller = $this->makeController(['Referer' => '/previous/page']);
        $response = $controller->testBack();
        $this->assertSame('/previous/page', $response->getHeaders()['Location']);
    }

    public function testBackWithHttpsExternalRefererRedirectsToRoot(): void
    {
        $controller = $this->makeController(['Referer' => 'https://evil.com/phishing']);
        $response = $controller->testBack();
        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    public function testBackWithHttpExternalRefererRedirectsToRoot(): void
    {
        $controller = $this->makeController(['Referer' => 'http://attacker.example.com/steal-creds']);
        $response = $controller->testBack();
        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    public function testBackWithProtocolRelativeUrlRedirectsToRoot(): void
    {
        $controller = $this->makeController(['Referer' => '//evil.com/path']);
        $response = $controller->testBack();
        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    // --- isSafeRedirect tests ---

    public function testRelativePathIsSafe(): void
    {
        $controller = $this->makeController();
        $this->assertTrue($controller->testIsSafeRedirect('/dashboard'));
        $this->assertTrue($controller->testIsSafeRedirect('/'));
        $this->assertTrue($controller->testIsSafeRedirect('/users?page=1'));
    }

    public function testAbsoluteUrlIsNotSafe(): void
    {
        $controller = $this->makeController();
        $this->assertFalse($controller->testIsSafeRedirect('https://example.com/path'));
        $this->assertFalse($controller->testIsSafeRedirect('http://example.com'));
    }

    public function testProtocolRelativeUrlIsNotSafe(): void
    {
        $controller = $this->makeController();
        $this->assertFalse($controller->testIsSafeRedirect('//evil.com'));
    }

    public function testEmptyStringIsNotSafe(): void
    {
        $controller = $this->makeController();
        $this->assertFalse($controller->testIsSafeRedirect(''));
    }

    public function testWhitespacePaddedRelativePathIsSafe(): void
    {
        $controller = $this->makeController();
        $this->assertTrue($controller->testIsSafeRedirect('  /path  '));
    }

    public function testWhitespacePaddedExternalUrlIsNotSafe(): void
    {
        $controller = $this->makeController();
        $this->assertFalse($controller->testIsSafeRedirect('  https://evil.com  '));
    }
}