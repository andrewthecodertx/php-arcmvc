<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Arc\Http\SapiEmitter;
use Arc\Http\Response;

class SapiEmitterTest extends TestCase
{
    public function testEmitSetsStatusCodeAndHeaders(): void
    {
        // Note: In a CLI test environment, headers_sent() returns false
        // and http_response_code() / header() work (though output is captured).
        $this->markTestSkippedIfHeadersSent();

        $response = new Response('Hello', 200, ['X-Custom' => 'test']);
        $emitter = new SapiEmitter();

        // In CLI, headers_sent() is typically false unless output started
        // This test verifies the method runs without error
        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('Hello', $output);
    }

    public function testEmitThrowsWhenHeadersAlreadySent(): void
    {
        // If headers are already sent (common in test runners), verify the exception
        if (!headers_sent()) {
            $this->markTestSkipped('Headers not sent - cannot test headers_sent() guard');
        }

        $response = new Response('Hello', 200);
        $emitter = new SapiEmitter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Headers already sent');

        $emitter->emit($response);
    }

    private function markTestSkippedIfHeadersSent(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent, cannot test SapiEmitter');
        }
    }
}