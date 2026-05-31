<?php

declare(strict_types=1);

namespace Arc\Http;

/**
 * Represents an HTTP response.
 *
 * Provides a fluent interface for setting status codes, headers, and content.
 * Redirect URLs are validated by default to prevent open redirect attacks.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    /** @var array<int, Cookie> */
    private array $cookies = [];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Queue a cookie to be emitted as its own Set-Cookie header.
     *
     * Cookies are stored separately from the header map so that multiple
     * cookies (e.g. CSRF + session + application) can coexist on one response;
     * a flat name => value header map would let them overwrite each other.
     */
    public function addCookie(Cookie $cookie): self
    {
        $this->cookies[] = $cookie;
        return $this;
    }

    /** @return array<int, Cookie> */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the response as JSON.
     *
     * @param array $data       Data to encode as JSON
     * @param int   $statusCode HTTP status code (default 200)
     * @throws \JsonException if encoding fails
     */
    public function json(array $data, int $statusCode = 200): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->statusCode = $statusCode;
        $this->content = json_encode($data, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Set the response as a redirect.
     *
     * By default, external URLs are rejected and replaced with '/' to prevent
     * open redirect attacks. Set $allowExternal to true for known-safe
     * external redirects (e.g., OAuth callbacks).
     *
     * @param string $url            Redirect target URL
     * @param int    $statusCode     HTTP status code (default 302)
     * @param bool   $allowExternal  Allow external URL targets (default false)
     */
    public function redirect(string $url, int $statusCode = 302, bool $allowExternal = false): self
    {
        if (!$allowExternal && !$this->isRelativeUrl($url)) {
            $url = '/';
        }

        $this->setHeader('Location', $url);
        $this->statusCode = $statusCode;
        $this->content = '';
        return $this;
    }

    /**
     * Determine if a URL is a safe relative path (not external).
     */
    private function isRelativeUrl(string $url): bool
    {
        $url = trim($url);

        // Relative paths starting with / (but not protocol-relative //)
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Anything with a scheme or host is external
        $parsed = parse_url($url);
        return !isset($parsed['host']) && !isset($parsed['scheme']);
    }
}