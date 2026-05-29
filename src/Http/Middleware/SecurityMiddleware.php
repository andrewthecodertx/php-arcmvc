<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

class SecurityMiddleware implements MiddlewareInterface
{
    private string $csp;
    private string $hsts;

    /**
     * @param string $csp  Content-Security-Policy header value. Defaults to a restrictive policy.
     * @param string $hsts Strict-Transport-Security header value. Defaults to 1 year with includeSubDomains.
     */
    public function __construct(string $csp = "default-src 'self'", string $hsts = 'max-age=31536000; includeSubDomains')
    {
        $this->csp = $csp;
        $this->hsts = $hsts;
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-XSS-Protection', '0');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Content-Security-Policy', $this->csp);
        $response->setHeader('Strict-Transport-Security', $this->hsts);

        return $response;
    }
}