<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

class SecurityMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-XSS-Protection', '0');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}