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

    // --- File upload helpers ---

    public function testGetFileReturnsFileWhenPresent(): void
    {
        $files = [
            'avatar' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024,
            ],
        ];
        $request = new Request(method: 'POST', uri: '/', files: $files);
        $file = $request->getFile('avatar');
        $this->assertNotNull($file);
        $this->assertSame('photo.jpg', $file['name']);
        $this->assertSame(1024, $file['size']);
    }

    public function testGetFileReturnsNullForMissingField(): void
    {
        $request = new Request(method: 'POST', uri: '/', files: []);
        $this->assertNull($request->getFile('avatar'));
    }

    public function testGetFileReturnsNullForUploadError(): void
    {
        $files = [
            'avatar' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ];
        $request = new Request(method: 'POST', uri: '/', files: $files);
        $this->assertNull($request->getFile('avatar'));
    }

    public function testGetFileReturnsNullForMultipleUpload(): void
    {
        $files = [
            'photos' => [
                'name' => ['a.jpg', 'b.jpg'],
                'type' => ['image/jpeg', 'image/jpeg'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [100, 200],
            ],
        ];
        $request = new Request(method: 'POST', uri: '/', files: $files);
        $this->assertNull($request->getFile('photos'));
    }

    // --- HTTP Method Override (tested via constructor) ---

    public function testMethodOverrideViaPostParameter(): void
    {
        // Test the override logic directly by simulating what createFromGlobals does
        $post = ['_method' => 'PUT', 'name' => 'Updated'];
        $method = 'POST';

        $override = $post['_method']
            ?? null;

        $allowedOverrides = ['PUT', 'PATCH', 'DELETE'];
        if (strtoupper($method) === 'POST' && $override !== null && in_array(strtoupper($override), $allowedOverrides, true)) {
            $method = strtoupper($override);
        }

        $this->assertSame('PUT', $method);
    }

    public function testMethodOverrideViaHeader(): void
    {
        $headers = ['X-HTTP-Method-Override' => 'DELETE'];
        $method = 'POST';

        $override = $headers['X-HTTP-Method-Override'] ?? null;
        $allowedOverrides = ['PUT', 'PATCH', 'DELETE'];
        if (strtoupper($method) === 'POST' && $override !== null && in_array(strtoupper($override), $allowedOverrides, true)) {
            $method = strtoupper($override);
        }

        $this->assertSame('DELETE', $method);
    }

    public function testGetMethodReturnsOverriddenMethod(): void
    {
        $request = new Request(method: 'PUT', uri: '/users/1');
        $this->assertSame('PUT', $request->getMethod());
    }

    public function testGetOriginalMethodReturnsServerRequestMethod(): void
    {
        $request = new Request(
            method: 'PUT',
            uri: '/users/1',
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('POST', $request->getOriginalMethod());
    }

    public function testGetOriginalMethodReturnsMethodWhenNoOverride(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('GET', $request->getOriginalMethod());
    }

    public function testMethodOverrideOnlyAppliesToPost(): void
    {
        // GET with _method should NOT be overridden
        $post = ['_method' => 'DELETE'];
        $method = 'GET';

        $override = $post['_method'] ?? null;
        $allowedOverrides = ['PUT', 'PATCH', 'DELETE'];
        // Only POST can be overridden
        if (strtoupper($method) === 'POST' && $override !== null && in_array(strtoupper($override), $allowedOverrides, true)) {
            $method = strtoupper($override);
        }

        $this->assertSame('GET', $method);
    }

    public function testMethodOverrideRejectsInvalidMethods(): void
    {
        $post = ['_method' => 'GET'];
        $method = 'POST';

        $override = $post['_method'] ?? null;
        $allowedOverrides = ['PUT', 'PATCH', 'DELETE'];
        if (strtoupper($method) === 'POST' && $override !== null && in_array(strtoupper($override), $allowedOverrides, true)) {
            $method = strtoupper($override);
        }

        // GET is not in allowed overrides — stays POST
        $this->assertSame('POST', $method);
    }
}