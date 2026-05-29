<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

use Arc\Http\MiddlewareInterface;
use Arc\Http\Request;
use Arc\Http\Response;

/**
 * CSRF protection middleware using the synchronizer token pattern.
 *
 * Generates a per-session token stored in a cookie, validates it on
 * unsafe HTTP methods (POST, PUT, PATCH, DELETE). Skips validation
 * for safe methods (GET, HEAD, OPTIONS).
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_LENGTH = 32;
    private const COOKIE_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-TOKEN';
    private const FORM_FIELD = '_token';
    public const TOKEN_ATTR = '_csrf_token';

    private string $cookieName;
    private string $headerName;
    private string $formField;

    public function __construct(
        ?string $cookieName = null,
        ?string $headerName = null,
        ?string $formField = null,
    ) {
        $this->cookieName = $cookieName ?? self::COOKIE_NAME;
        $this->headerName = $headerName ?? self::HEADER_NAME;
        $this->formField = $formField ?? self::FORM_FIELD;
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->resolveToken($request);

        // Attach the token to the request so controllers/views can access it
        $request->setAttribute(self::TOKEN_ATTR, $token);

        if ($this->isUnsafeMethod($request->getMethod())) {
            $submitted = $this->getSubmittedToken($request);

            if (!$this->validateToken($token, $submitted)) {
                return new Response('CSRF token mismatch', 419, ['Content-Type' => 'text/html']);
            }
        }

        $response = $next($request);

        // Set the token cookie so it persists across requests
        if ($request->getCookie($this->cookieName) === null) {
            $response->setHeader('Set-Cookie', "{$this->cookieName}={$token}; Path=/; SameSite=Strict; HttpOnly");
        }

        return $response;
    }

    /**
     * Generate a CSRF token hidden input field for use in forms.
     */
    public static function field(Request $request, ?string $formField = null): string
    {
        $token = $request->getAttribute(self::TOKEN_ATTR, '');
        $field = $formField ?? self::FORM_FIELD;
        $escaped = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . $field . '" value="' . $escaped . '">';
    }

    /**
     * Get the CSRF token value from the request.
     */
    public static function token(Request $request): string
    {
        return $request->getAttribute(self::TOKEN_ATTR, '');
    }

    private function resolveToken(Request $request): string
    {
        $cookie = $request->getCookie($this->cookieName);

        if ($cookie !== null && $this->isValidTokenFormat($cookie)) {
            return $cookie;
        }

        return $this->generateToken();
    }

    private function isUnsafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    private function validateToken(string $expected, ?string $submitted): bool
    {
        if ($submitted === null) {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    private function getSubmittedToken(Request $request): ?string
    {
        // Check form data first, then header, then JSON body
        $formToken = $request->getPost($this->formField);
        if ($formToken !== null) {
            return $formToken;
        }

        $headerToken = $request->getHeader($this->headerName);
        if ($headerToken !== null) {
            return $headerToken;
        }

        $bodyToken = $request->getBody($this->formField);
        if ($bodyToken !== null && is_string($bodyToken)) {
            return $bodyToken;
        }

        return null;
    }

    private function isValidTokenFormat(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
    }
}