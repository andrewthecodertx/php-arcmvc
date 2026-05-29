<?php

declare(strict_types=1);

namespace Arc\Database;

class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    private static ?Connection $connection = null;

    /**
     * Validate that an identifier (table name, column name) contains only
     * safe characters to prevent SQL injection via string interpolation.
     */
    private static function isValidIdentifier(string $identifier): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) === 1;
    }

    /**
     * Assert that an identifier is valid, throwing if it is not.
     */
    private static function assertValidIdentifier(string $identifier, string $context): void
    {
        if (!self::isValidIdentifier($identifier)) {
            throw new \InvalidArgumentException(
                "Invalid SQL identifier in {$context}: {$identifier}"
            );
        }
    }

    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    public static function getConnection(): Connection
    {
        if (static::$connection === null) {
            throw new \RuntimeException('No database connection set. Call Model::setConnection() or register via Application.');
        }
        return static::$connection;
    }

    public static function all(): array
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        return static::getConnection()->select("SELECT * FROM `{$instance->table}`");
    }

    public static function find(int|string $id): ?array
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        self::assertValidIdentifier($instance->primaryKey, 'primary key');
        return static::getConnection()->selectOne(
            "SELECT * FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = :id LIMIT 1",
            ['id' => $id],
        );
    }

    public static function where(string $column, mixed $value): array
    {
        self::assertValidIdentifier($column, 'column name');
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        return static::getConnection()->select(
            "SELECT * FROM `{$instance->table}` WHERE `{$column}` = :value",
            ['value' => $value],
        );
    }

    public static function create(array $data): int
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        $data = $instance->filterFillable($data);

        if (empty($data)) {
            throw new \RuntimeException('No fillable attributes provided.');
        }

        foreach (array_keys($data) as $col) {
            self::assertValidIdentifier($col, 'column name');
        }

        $columns = implode(', ', array_map(fn (string $col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_map(fn (string $col) => ":{$col}", array_keys($data)));

        return static::getConnection()->insert(
            "INSERT INTO `{$instance->table}` ({$columns}) VALUES ({$placeholders})",
            $data,
        );
    }

    public static function update(int|string $id, array $data): int
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        self::assertValidIdentifier($instance->primaryKey, 'primary key');
        $data = $instance->filterFillable($data);

        foreach (array_keys($data) as $col) {
            self::assertValidIdentifier($col, 'column name');
        }

        $sets = implode(', ', array_map(fn (string $col) => "`{$col}` = :{$col}", array_keys($data)));
        $data['id'] = $id;

        return static::getConnection()->update(
            "UPDATE `{$instance->table}` SET {$sets} WHERE `{$instance->primaryKey}` = :id",
            $data,
        );
    }

    public static function delete(int|string $id): int
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        self::assertValidIdentifier($instance->primaryKey, 'primary key');
        return static::getConnection()->delete(
            "DELETE FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = :id",
            ['id' => $id],
        );
    }

    public static function count(string $column = '*'): int
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');
        if ($column !== '*') {
            self::assertValidIdentifier($column, 'column name');
        }
        $result = static::getConnection()->selectOne(
            "SELECT COUNT({$column}) as count FROM `{$instance->table}`",
        );
        return (int) ($result['count'] ?? 0);
    }

    public static function query(string $sql, array $bindings = []): array
    {
        return static::getConnection()->select($sql, $bindings);
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}