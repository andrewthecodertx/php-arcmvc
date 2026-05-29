<?php

declare(strict_types=1);

namespace Arc\Http\Middleware;

/**
 * Interface for rate limit storage backends.
 * Implement Redis, database, or other persistent stores for multi-process deployments.
 */
interface RateLimitStoreInterface
{
    /**
     * Increment the counter for the given key and return the current hit count.
     * If the key does not exist or has expired, start a new window.
     */
    public function increment(string $key, int $windowSeconds): int;
}