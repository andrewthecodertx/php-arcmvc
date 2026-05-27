<?php

declare(strict_types=1);

namespace Arc\Http;

class Request
{
    private array $attributes = [];

    public function __construct(
        private string $method = '',
        private string $uri = '',
        private array $headers = [],
        private array $query = [],
        private array $post = [],
        private array $body = [],
        private array $cookies = [],
        private array $files = [],
        private array $server = [],
    ) {
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? $headers : [];

        $query = [];
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        $cookies = $_COOKIE;
        $files = $_FILES;
        $server = $_SERVER;

        $body = [];
        $rawInput = file_get_contents('php://input');
        $contentType = $headers['Content-Type'] ?? ($headers['content-type'] ?? '');
        if ($rawInput && str_contains($contentType, 'application/json')) {
            $body = json_decode($rawInput, true) ?? [];
        }

        return new self(
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $query,
            post: $_POST,
            body: $body,
            cookies: $cookies,
            files: $files,
            server: $server,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }
        return null;
    }

    public function getQuery(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? null;
    }

    public function getPost(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? null;
    }

    public function getBody(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? null;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getCookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getServer(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? null;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        return str_contains($contentType, 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->getHeader('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }
}