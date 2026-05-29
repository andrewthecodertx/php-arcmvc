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

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();

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