<?php

declare(strict_types=1);

namespace Arc\Database;

use RuntimeException;

/**
 * Thrown when a database operation fails.
 * Wraps PDOException to hide sensitive database details in production.
 */
class DatabaseException extends RuntimeException
{
    private string $query;

    public function __construct(string $message, string $query = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->query = $query;
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}