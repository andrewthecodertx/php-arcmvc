<?php

declare(strict_types=1);

namespace Arc\Http;

/**
 * Thin wrapper around PHP's native session functions.
 *
 * Provides a testable, object-oriented interface for session management.
 * Starts the session lazily on first access.
 */
class Session
{
    private bool $started = false;

    /**
     * Start the session if not already started.
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        // Apply secure cookie defaults explicitly rather than relying on php.ini.
        // Secure is enabled when the request arrived over HTTPS so local HTTP
        // development still works.
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->requestIsSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->started = true;
    }

    private function requestIsSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * Get a session value by key, with an optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists.
     */
    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a session key.
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Flash a value for the next request, then clear it.
     * Returns the value if it exists, or the default if not.
     */
    public function flash(string $key, mixed $default = null): mixed
    {
        $value = $this->get("_flash:{$key}", $default);
        $this->remove("_flash:{$key}");
        return $value;
    }

    /**
     * Set a flash value that persists for one request.
     */
    public function setFlash(string $key, mixed $value): void
    {
        $this->set("_flash:{$key}", $value);
    }

    /**
     * Check if a flash key exists.
     */
    public function hasFlash(string $key): bool
    {
        return $this->has("_flash:{$key}");
    }

    /**
     * Get all session data.
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    /**
     * Regenerate the session ID to prevent session fixation attacks.
     */
    public function regenerate(bool $deleteOld = true): void
    {
        $this->start();
        session_regenerate_id($deleteOld);
    }

    /**
     * Destroy the session entirely.
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Expire the session cookie itself so the client stops sending it.
            if (!headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }

            session_destroy();
            $this->started = false;
        }
    }

    /**
     * Get the current session ID.
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Check if the session has been started.
     */
    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }
}