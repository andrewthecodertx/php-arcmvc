<?php

declare(strict_types=1);

namespace Arc\Http;

/**
 * Represents an HTTP request.
 *
 * Wraps all request data: method, URI, headers, query params, POST data,
 * JSON body, cookies, files, and server variables. Also supports arbitrary
 * attributes for middleware to attach request-scoped data.
 */
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

    /**
     * Create a Request from PHP superglobals ($_SERVER, $_GET, $_POST, etc.).
     *
     * Supports HTTP method override for browser forms:
     * - Via `_method` POST parameter (hidden form field)
     * - Via `X-HTTP-Method-Override` header (for API clients)
     *
     * Only POST requests can be overridden to PUT, PATCH, or DELETE.
     */
    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? $headers : [];

        $query = [];
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        $post = $_POST;
        $cookies = $_COOKIE;
        $files = $_FILES;
        $server = $_SERVER;

        $body = [];
        $rawInput = file_get_contents('php://input');
        $contentType = $headers['Content-Type'] ?? ($headers['content-type'] ?? '');
        if ($rawInput && str_contains($contentType, 'application/json')) {
            $body = json_decode($rawInput, true) ?? [];
        }

        // HTTP method override: allows PUT/PATCH/DELETE from browser forms
        $allowedOverrides = ['PUT', 'PATCH', 'DELETE'];
        if (strtoupper($method) === 'POST') {
            $override = $post['_method']
                ?? $headers['X-HTTP-Method-Override']
                ?? $headers['x-http-method-override']
                ?? null;

            if ($override !== null && in_array(strtoupper($override), $allowedOverrides, true)) {
                $method = strtoupper($override);
            }
        }

        return new self(
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $query,
            post: $post,
            body: $body,
            cookies: $cookies,
            files: $files,
            server: $server,
        );
    }

    /** Get the HTTP method (GET, POST, etc.). May be overridden from POST via _method or header. */
    public function getMethod(): string
    {
        return $this->method;
    }

    /** Get the original HTTP method before any override was applied. */
    public function getOriginalMethod(): string
    {
        // Stored in server as the true original method
        return strtoupper($this->server['REQUEST_METHOD'] ?? $this->method);
    }

    /** Get the full request URI including query string. */
    public function getUri(): string
    {
        return $this->uri;
    }

    /** Get the path component of the URI (no query string). */
    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    /** Get all headers as an associative array. */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a single header value by name (case-insensitive).
     * Returns null if the header is not present.
     */
    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Get query string parameters.
     * With a key, returns the value or null. Without, returns all params.
     */
    public function getQuery(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? null;
    }

    /**
     * Get POST/form data.
     * With a key, returns the value or null. Without, returns all data.
     */
    public function getPost(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? null;
    }

    /**
     * Get parsed JSON body data.
     * With a key, returns the value or null. Without, returns all data.
     */
    public function getBody(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? null;
    }

    /** Get all cookies. */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /** Get a single cookie value by name. Returns null if not set. */
    public function getCookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** Get all uploaded files (raw $_FILES array). */
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
     * @param string $name          Form field name
     * @param int     $maxBytes     Maximum file size in bytes (default 2MB)
     * @param array   $allowedMimes Allowed MIME types (empty = any)
     * @return array  Validated file array from $_FILES with added 'safe_name' key
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

        // MIME type validation via fileinfo
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

        $file['safe_name'] = $this->sanitizeFileName($file['name']);

        return $file;
    }

    /** Sanitize a filename by stripping directory paths and null bytes. */
    private function sanitizeFileName(string $name): string
    {
        $name = basename($name);
        $name = str_replace(["\0", '\\', '/', '..'], '', $name);
        return $name;
    }

    /** Get server variables. With a key, returns the value or null. */
    public function getServer(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? null;
    }

    /** Check if the request method matches (case-insensitive). */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    /** Check if the request Content-Type is application/json. */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        return str_contains($contentType, 'application/json');
    }

    /** Check if the client expects a JSON response (Accept header). */
    public function wantsJson(): bool
    {
        $accept = $this->getHeader('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }

    /** Set a request attribute (for middleware to pass data down the pipeline). */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /** Get a request attribute set by middleware. */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** Get all request attributes. */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the client IP address.
     *
     * By default returns REMOTE_ADDR. If the immediate peer (REMOTE_ADDR) is a
     * configured trusted proxy, the X-Forwarded-For chain is walked from right
     * to left and the first address that is not itself a trusted proxy is
     * returned. X-Forwarded-For is never honored from untrusted peers, so the
     * key cannot be spoofed by arbitrary clients.
     *
     * @param array<int, string> $trustedProxies IPs allowed to set X-Forwarded-For
     */
    public function ip(array $trustedProxies = []): ?string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? null;

        if ($remote === null || $trustedProxies === [] || !in_array($remote, $trustedProxies, true)) {
            return $remote;
        }

        $forwarded = $this->getHeader('X-Forwarded-For');
        if ($forwarded === null || $forwarded === '') {
            return $remote;
        }

        $chain = array_map('trim', explode(',', $forwarded));
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!in_array($chain[$i], $trustedProxies, true)) {
                return $chain[$i];
            }
        }

        return $chain[0] ?? $remote;
    }

    /**
     * Determine whether the request was made over HTTPS.
     *
     * Recognizes a direct TLS connection (HTTPS server var or port 443).
     * X-Forwarded-Proto is only trusted when the immediate peer is one of the
     * supplied trusted proxies.
     *
     * @param array<int, string> $trustedProxies IPs allowed to set X-Forwarded-Proto
     */
    public function isSecure(array $trustedProxies = []): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        if ((string) ($this->server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        $remote = $this->server['REMOTE_ADDR'] ?? null;
        if ($remote !== null && in_array($remote, $trustedProxies, true)) {
            $proto = $this->getHeader('X-Forwarded-Proto');
            if ($proto !== null && strtolower(trim(explode(',', $proto)[0])) === 'https') {
                return true;
            }
        }

        return false;
    }
}