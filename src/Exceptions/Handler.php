<?php

declare(strict_types=1);

namespace Arc\Exceptions;

use Arc\Http\Request;
use Arc\Http\Response;
use Throwable;

class Handler
{
    private bool $debug;

    public function __construct(?bool $debug = null)
    {
        $this->debug = $debug ?? (bool) ($_ENV['APP_DEBUG'] ?? false);
    }

    public function handle(Throwable $e): Response
    {
        if ($this->debug) {
            return $this->renderDebug($e);
        }

        return $this->renderProduction($e);
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

    private function renderProduction(Throwable $e): Response
    {
        error_log("[Arc] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

        $status = $this->statusCodeFrom($e);

        return new Response(
            '<html><body><h1>Something went wrong</h1></body></html>',
            $status,
            ['Content-Type' => 'text/html'],
        );
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