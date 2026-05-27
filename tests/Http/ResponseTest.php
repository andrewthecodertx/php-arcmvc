<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Arc\Http\Response;

class ResponseTest extends TestCase
{
    public function testDefaultResponse(): void
    {
        $response = new Response('Hello', 200);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello', $response->getContent());
    }

    public function testFluentSetters(): void
    {
        $response = new Response();
        $result = $response->setStatusCode(201)->setHeader('X-Custom', 'test')->setContent('body');
        $this->assertSame($response, $result);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('test', $response->getHeaders()['X-Custom']);
        $this->assertSame('body', $response->getContent());
    }

    public function testJsonResponse(): void
    {
        $response = new Response();
        $response->json(['status' => 'ok'], 201);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame('{"status":"ok"}', $response->getContent());
    }

    public function testRedirectResponse(): void
    {
        $response = new Response();
        $response->redirect('/other', 301);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/other', $response->getHeaders()['Location']);
        $this->assertSame('', $response->getContent());
    }
}