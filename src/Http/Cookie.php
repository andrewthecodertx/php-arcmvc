<?php

declare(strict_types=1);

namespace Arc\Http;

/**
 * Immutable representation of an HTTP cookie.
 *
 * Centralizes cookie attribute handling (Secure, HttpOnly, SameSite, Max-Age,
 * Path, Domain) so multiple cookies can be emitted on a single response without
 * the name => value header collision that a flat header map suffers from.
 */
final readonly class Cookie
{
    public function __construct(
        public string $name,
        public string $value,
        public string $path = '/',
        public bool $secure = false,
        public bool $httpOnly = true,
        public string $sameSite = 'Strict',
        public ?int $maxAge = null,
        public ?string $domain = null,
    ) {
    }

    /**
     * Render the cookie as a Set-Cookie header value (without the field name).
     */
    public function toHeader(): string
    {
        $parts = [$this->name . '=' . $this->value];

        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }

        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . $this->maxAge;
        }

        if ($this->sameSite !== '') {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        return implode('; ', $parts);
    }
}
