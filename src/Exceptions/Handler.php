<?php

declare(strict_types=1);

namespace Arc\Exceptions;

use Arc\Http\Request;
use Arc\Http\Response;
use Psr\Log\LoggerInterface;
use Throwable;

class Handler
{
    private bool $debug;

    public function __construct(?bool $debug = null, private ?LoggerInterface $logger = null)
    {
        $this->debug = $debug ?? (bool) ($_ENV['APP_DEBUG'] ?? false);
    }

    public function handle(Throwable $e, ?Request $request = null): Response
    {
        $status = $this->statusCodeFrom($e);
        $wantsJson = $request !== null && $request->wantsJson();

        if ($this->debug) {
            return $wantsJson
                ? $this->renderJson($e, $status, debug: true)
                : $this->renderDebug($e);
        }

        $this->log($e);

        return $wantsJson
            ? $this->renderJson($e, $status, debug: false)
            : $this->renderProductionHtml($status);
    }

    private function renderDebug(Throwable $e): Response
    {
        $content = sprintf(
            '<html><body>'
            . '<h1>%s</h1>'
            . '<p><strong>Message:</strong> %s</p>'
            . '<p><strong>File:</strong> %s, line %d</p>'
            . '<h2>Stack Trace</h2>'
            . '<pre>%s</pre>'
            . '</body></html>',
            htmlspecialchars($e::class),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString()),
        );

        $status = $this->statusCodeFrom($e);

        return new Response($content, $status, ['Content-Type' => 'text/html']);
    }

    private function renderProductionHtml(int $status): Response
    {
        return new Response(
            '<html><body><h1>Something went wrong</h1></body></html>',
            $status,
            ['Content-Type' => 'text/html'],
        );
    }

    /**
     * Render an RFC 7807-style problem document for API clients. The message
     * and trace are only included in debug mode to avoid leaking internals.
     */
    private function renderJson(Throwable $e, int $status, bool $debug): Response
    {
        $body = ['status' => $status, 'title' => $this->titleFor($status)];

        if ($debug) {
            $body['detail'] = $e->getMessage();
            $body['exception'] = $e::class;
        }

        $response = new Response();
        $response->json($body, $status);
        $response->setHeader('Content-Type', 'application/problem+json');
        return $response;
    }

    private function log(Throwable $e): void
    {
        $message = "{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";

        if ($this->logger !== null) {
            $this->logger->error($message, ['exception' => $e]);
            return;
        }

        error_log("[Arc] {$message}");
    }

    private function titleFor(int $status): string
    {
        return match ($status) {
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            default => 'Internal Server Error',
        };
    }

    private function statusCodeFrom(Throwable $e): int
    {
        if ($e instanceof \Arc\Validation\ValidationException) {
            return 422;
        }
        if ($e instanceof \Arc\Routing\RouteNotFoundException) {
            return 404;
        }

        $code = $e->getCode();
        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
    }
}