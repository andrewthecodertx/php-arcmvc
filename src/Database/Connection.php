<?php

declare(strict_types=1);

namespace Arc\Database;

use PDO;
use PDOStatement;

class Connection
{
    private ?PDO $pdo = null;

    public function __construct(
        private string $driver,
        private string $host = '',
        private string $database = '',
        private string $username = '',
        private string $password = '',
        private string $port = '',
        private string $charset = '',
        private array $options = [],
    ) {
    }

    public static function make(array $config): self
    {
        return new self(
            driver: $config['driver'] ?? 'mysql',
            host: $config['host'] ?? '127.0.0.1',
            database: $config['database'] ?? '',
            username: $config['username'] ?? '',
            password: $config['password'] ?? '',
            port: $config['port'] ?? '',
            charset: $config['charset'] ?? '',
            options: $config['options'] ?? [],
        );
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO(
                $this->dsn(),
                $this->username,
                $this->password,
                $this->defaultOptions(),
            );
        }
        return $this->pdo;
    }

    /**
     * Check if the database connection is alive.
     * Returns true if connected, false if the connection has been lost.
     */
    public function ping(): bool
    {
        try {
            $this->getPdo()->query('SELECT 1');
            return true;
        } catch (\PDOException) {
            $this->pdo = null;
            return false;
        }
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database query failed: ' . $e->getMessage(),
                $sql,
                $e,
            );
        }
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $result = $this->query($sql, $bindings)->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    public function insert(string $sql, array $bindings = []): int
    {
        $this->query($sql, $bindings);
        return (int) $this->getPdo()->lastInsertId();
    }

    public function update(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->getPdo()->beginTransaction();
        try {
            $result = $callback($this);
            $this->getPdo()->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->getPdo()->rollBack();
            throw $e;
        }
    }

    private function dsn(): string
    {
        if ($this->driver === 'sqlite') {
            return "sqlite:{$this->database}";
        }

        $dsn = "{$this->driver}:host={$this->host}";

        if ($this->port) {
            $dsn .= ";port={$this->port}";
        }

        $dsn .= ";dbname={$this->database}";

        if ($this->charset) {
            $dsn .= ";charset={$this->charset}";
        }

        return $dsn;
    }

    private function defaultOptions(): array
    {
        return array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ], $this->options);
    }
}