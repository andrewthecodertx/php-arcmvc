<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

/**
 * In-memory rate limit store using a sliding window.
 *
 * Suitable for single-process deployments. For multi-process or distributed
 * environments, use a Redis or database-backed implementation of RateLimitStoreInterface.
 */
class InMemoryRateLimitStore implements RateLimitStoreInterface
{
    /** @var array<string, array{count: int, expires: int}> */
    private array $buckets = [];

    private static bool $warned = false;

    public function __construct()
    {
        // This store is per-process and non-persistent: under PHP-FPM with N
        // workers the effective limit is ~N× the configured value, and state is
        // lost on restart. Warn once when used outside the CLI so it is not
        // silently relied upon in production.
        if (PHP_SAPI !== 'cli' && !self::$warned) {
            self::$warned = true;
            trigger_error(
                'InMemoryRateLimitStore is per-process and not suitable for production. '
                . 'Use a shared store (Redis/database) behind a multi-worker SAPI.',
                E_USER_WARNING
            );
        }
    }

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();

        // Probabilistically reclaim expired buckets so memory stays bounded
        // even when no external scheduler calls gc().
        if (random_int(1, 100) === 1) {
            $this->gc();
        }

        if (!isset($this->buckets[$key]) || $this->buckets[$key]['expires'] <= $now) {
            $this->buckets[$key] = [
                'count' => 1,
                'expires' => $now + $windowSeconds,
            ];
            return 1;
        }

        $this->buckets[$key]['count']++;
        return $this->buckets[$key]['count'];
    }

    /**
     * Remove expired buckets. Called periodically to prevent memory leaks.
     */
    public function gc(): void
    {
        $now = time();
        foreach ($this->buckets as $key => $bucket) {
            if ($bucket['expires'] <= $now) {
                unset($this->buckets[$key]);
            }
        }
    }
}