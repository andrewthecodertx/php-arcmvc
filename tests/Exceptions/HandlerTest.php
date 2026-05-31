<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use Arc\Exceptions\Handler;
use Arc\Http\Request;
use Arc\Routing\RouteNotFoundException;

class HandlerTest extends TestCase
{
    public function testProductionRendersHtmlByDefault(): void
    {
        $handler = new Handler(debug: false);
        $response = $handler->handle(new \RuntimeException('boom'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeaders()['Content-Type']);
        $this->assertStringNotContainsString('boom', $response->getContent());
    }

    public function testJsonRequestGetsProblemJson(): void
    {
        $handler = new Handler(debug: false);
        $request = new Request(method: 'GET', uri: '/api', headers: ['Accept' => 'application/json']);

        $response = $handler->handle(new RouteNotFoundException('/api'), $request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->getHeaders()['Content-Type']);

        $body = json_decode($response->getContent(), true);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Not Found', $body['title']);
        // Production JSON must not leak the exception detail.
        $this->assertArrayNotHasKey('detail', $body);
    }

    public function testDebugJsonIncludesDetail(): void
    {
        $handler = new Handler(debug: true);
        $request = new Request(method: 'GET', uri: '/api', headers: ['Accept' => 'application/json']);

        $response = $handler->handle(new \RuntimeException('explosive detail'), $request);

        $body = json_decode($response->getContent(), true);
        $this->assertSame('explosive detail', $body['detail']);
        $this->assertSame(\RuntimeException::class, $body['exception']);
    }

    public function testInjectedLoggerReceivesError(): void
    {
        $logger = new class extends \Psr\Log\AbstractLogger {
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message];
            }
        };

        $handler = new Handler(debug: false, logger: $logger);
        $handler->handle(new \RuntimeException('logged failure'));

        $this->assertCount(1, $logger->records);
        $this->assertStringContainsString('logged failure', $logger->records[0][1]);
    }
}
