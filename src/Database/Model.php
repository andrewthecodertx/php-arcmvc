<?php

declare(strict_types=1);

namespace Arc\Database;

/**
 * Base model class for Active Record-style database operations.
 *
 * Subclasses set $table, $primaryKey, and $fillable to configure behavior.
 * All methods that interpolate identifiers (table/column names) validate
 * against `/^[a-zA-Z_][a-zA-Z0-9_]*$/` to prevent SQL injection.
 *
 * @throws \InvalidArgumentException if an identifier contains invalid characters
 * @throws \RuntimeException if no database connection is configured
 */
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
     * @throws \InvalidArgumentException
     */
    private static function assertValidIdentifier(string $identifier, string $context): void
    {
        if (!self::isValidIdentifier($identifier)) {
            throw new \InvalidArgumentException(
                "Invalid SQL identifier in {$context}: {$identifier}"
            );
        }
    }

    /** Set the database connection for all model operations. */
    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Get the configured database connection.
     * @throws \RuntimeException if no connection has been set
     */
    public static function getConnection(): Connection
    {
        if (static::$connection === null) {
            throw new \RuntimeException('No database connection set. Call Model::setConnection() or register via Application.');
        }
        return static::$connection;
    }

    /**
     * Retrieve rows with pagination.
     * Default limit is 1000 to prevent memory exhaustion on large tables.
     */
    public static function all(int $limit = 1000, int $offset = 0): array
    {
        $instance = new static();
        self::assertValidIdentifier($instance->table, 'table name');

        // LIMIT/OFFSET are inlined rather than bound: with native prepares
        // (ATTR_EMULATE_PREPARES => false) MySQL rejects string-bound integers
        // in LIMIT clauses. These values are int-typed parameters, so inlining
        // them is injection-safe.
        $limit = max(0, $limit);
        $offset = max(0, $offset);

        return static::getConnection()->select(
            "SELECT * FROM `{$instance->table}` LIMIT {$limit} OFFSET {$offset}",
        );
    }

    /**
     * Find a single row by primary key.
     * @return array|null The row data, or null if not found
     */
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

    /**
     * Find rows matching a column value.
     * @throws \InvalidArgumentException if $column contains invalid characters
     */
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

    /**
     * Insert a new row. Only fillable attributes are stored.
     * @throws \InvalidArgumentException if column names contain invalid characters
     * @throws \RuntimeException if no fillable attributes are provided
     */
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

    /**
     * Update rows by primary key. Only fillable attributes are stored.
     * @throws \InvalidArgumentException if column names contain invalid characters
     */
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

        // Use a reserved placeholder for the WHERE clause so it never collides
        // with a fillable column literally named after the primary key.
        $data['__pk'] = $id;

        return static::getConnection()->update(
            "UPDATE `{$instance->table}` SET {$sets} WHERE `{$instance->primaryKey}` = :__pk",
            $data,
        );
    }

    /**
     * Delete a row by primary key.
     * @return int Number of affected rows
     */
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

    /**
     * Count rows, optionally for a specific column.
     * @param string $column Column to count (default '*' for all rows)
     * @throws \InvalidArgumentException if $column is not '*' and contains invalid characters
     */
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

    /**
     * Execute a raw SQL query with parameter bindings.
     * Use with caution: no identifier validation is performed.
     */
    public static function query(string $sql, array $bindings = []): array
    {
        return static::getConnection()->select($sql, $bindings);
    }

    /**
     * Filter $data to only include keys listed in $fillable.
     * If $fillable is empty, all data is passed through.
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}