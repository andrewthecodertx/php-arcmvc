<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Arc\Http\Request;

class RequestTest extends TestCase
{
    public function testGetMethod(): void
    {
        $request = new Request(method: 'GET', uri: '/test');
        $this->assertSame('GET', $request->getMethod());
    }

    public function testGetPath(): void
    {
        $request = new Request(method: 'GET', uri: '/test?q=1');
        $this->assertSame('/test', $request->getPath());
    }

    public function testGetUri(): void
    {
        $request = new Request(method: 'GET', uri: '/test?q=1');
        $this->assertSame('/test?q=1', $request->getUri());
    }

    public function testQueryAccess(): void
    {
        $request = new Request(method: 'GET', uri: '/', query: ['page' => '2']);
        $this->assertSame('2', $request->getQuery('page'));
        $this->assertSame(['page' => '2'], $request->getQuery());
    }

    public function testPostAccess(): void
    {
        $request = new Request(method: 'POST', uri: '/', post: ['name' => 'Arc']);
        $this->assertSame('Arc', $request->getPost('name'));
    }

    public function testBodyAccess(): void
    {
        $request = new Request(method: 'POST', uri: '/', body: ['key' => 'value']);
        $this->assertSame('value', $request->getBody('key'));
    }

    public function testHeaderAccessCaseInsensitive(): void
    {
        $request = new Request(method: 'GET', uri: '/', headers: ['Content-Type' => 'application/json']);
        $this->assertSame('application/json', $request->getHeader('content-type'));
    }

    public function testIsMethod(): void
    {
        $request = new Request(method: 'POST', uri: '/');
        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('GET'));
    }

    public function testAttributes(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $request->setAttribute('user_id', 42);
        $this->assertSame(42, $request->getAttribute('user_id'));
        $this->assertNull($request->getAttribute('missing'));
    }

    public function testCookieAccess(): void
    {
        $request = new Request(method: 'GET', uri: '/', cookies: ['session' => 'abc123']);
        $this->assertSame('abc123', $request->getCookie('session'));
    }
}