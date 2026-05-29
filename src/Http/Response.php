<?php

declare(strict_types=1);

namespace Arc\Http;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

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

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function json(array $data, int $statusCode = 200): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->statusCode = $statusCode;
        $this->content = json_encode($data, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Redirect to a URL. External URLs are rejected by default to prevent
     * open redirect attacks. Set $allowExternal to true only when the
     * redirect target is known-safe (e.g., developer-defined).
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
