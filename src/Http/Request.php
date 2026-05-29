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

    /**
     * Get a single uploaded file by name, or null if not present.
     * Returns null if the file had an upload error.
     */
    public function getFile(string $name): ?array
    {
        if (!isset($this->files[$name])) {
            return null;
        }

        $file = $this->files[$name];

        // Multiple file upload: return null, use getFiles() instead
        if (is_array($file['name'])) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        return $file;
    }

    /**
     * Validate a single uploaded file against constraints.
     *
     * @param string $name     Form field name
     * @param int     $maxBytes Maximum file size in bytes (default 2MB)
     * @param array   $allowedMimes Allowed MIME types (empty = any)
     * @return array  Validated file array from $_FILES
     * @throws \InvalidArgumentException if validation fails
     */
    public function validateFile(string $name, int $maxBytes = 2097152, array $allowedMimes = []): array
    {
        $file = $this->getFile($name);

        if ($file === null) {
            throw new \InvalidArgumentException("No valid upload for field '{$name}'");
        }

        if ($file['size'] > $maxBytes) {
            throw new \InvalidArgumentException(
                "File '{$name}' exceeds maximum size of {$maxBytes} bytes (got {$file['size']})"
            );
        }

        // Verify the file is an actual uploaded file (not a path traversal attack)
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException("File '{$name}' is not a valid upload");
        }

        // MIME type validation
        if (!empty($allowedMimes)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($file['tmp_name']);

            if (!in_array($detectedMime, $allowedMimes, true)) {
                $allowed = implode(', ', $allowedMimes);
                throw new \InvalidArgumentException(
                    "File '{$name}' has unsupported MIME type '{$detectedMime}' (allowed: {$allowed})"
                );
            }
        }

        // Sanitize filename: strip path components and null bytes
        $file['safe_name'] = $this->sanitizeFileName($file['name']);

        return $file;
    }

    /**
     * Sanitize a filename by stripping directory paths and null bytes.
     */
    private function sanitizeFileName(string $name): string
    {
        // Strip directory components
        $name = basename($name);
        // Remove null bytes and other dangerous characters
        $name = str_replace(["\0", '\\', '/', '..'], '', $name);
        return $name;
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