<?php

declare(strict_types=1);

namespace Arc\Database;

/**
 * Base model class for Active Record-style database operations.
 *
 * Subclasses set $table, $primaryKey, and $fillable to configure behavior.
 * Static convenience methods delegate to QueryBuilder for all queries.
 */
class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    private static ?Connection $connection = null;

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
            throw new \RuntimeException('No database connection set. Call Model::setConnection() first.');
        }
        return static::$connection;
    }

    /**
     * Create a new QueryBuilder for this model's table.
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();
        QueryBuilder::assertValidIdentifier($instance->table, 'table name');
        return new QueryBuilder(static::getConnection(), $instance->table);
    }

    /**
     * Retrieve rows with pagination.
     */
    public static function all(int $limit = 1000, int $offset = 0): array
    {
        return static::query()->limit($limit)->offset($offset)->get();
    }

    /**
     * Find a single row by primary key.
     * @return array|null The row data, or null if not found
     */
    public static function find(int|string $id): ?array
    {
        $instance = new static();
        QueryBuilder::assertValidIdentifier($instance->primaryKey, 'primary key');

        return static::query()->where($instance->primaryKey, $id)->first();
    }

    /**
     * Find a single row by primary key or throw.
     * @throws \RuntimeException if the row is not found
     */
    public static function findOrFail(int|string $id): array
    {
        $result = static::find($id);

        if ($result === null) {
            $instance = new static();
            throw new \RuntimeException("No {$instance->table} found with {$instance->primaryKey} = {$id}");
        }

        return $result;
    }

    /**
     * Find rows matching a column value.
     */
    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): array
    {
        if ($value === null) {
            return static::query()->where($column, $operatorOrValue)->get();
        }

        return static::query()->where($column, $operatorOrValue, $value)->get();
    }

    /**
     * Insert a new row. Only fillable attributes are stored.
     * @throws \RuntimeException if no fillable attributes are provided
     */
    public static function create(array $data): int
    {
        $instance = new static();
        $data = $instance->filterFillable($data);

        if (empty($data)) {
            throw new \RuntimeException('No fillable attributes provided.');
        }

        return static::query()->insert($data);
    }

    /**
     * Update rows by primary key. Only fillable attributes are stored.
     */
    public static function update(int|string $id, array $data): int
    {
        $instance = new static();
        QueryBuilder::assertValidIdentifier($instance->primaryKey, 'primary key');
        $data = $instance->filterFillable($data);

        return static::query()->where($instance->primaryKey, $id)->update($data);
    }

    /**
     * Delete a row by primary key.
     * @return int Number of affected rows
     */
    public static function delete(int|string $id): int
    {
        $instance = new static();
        QueryBuilder::assertValidIdentifier($instance->primaryKey, 'primary key');

        return static::query()->where($instance->primaryKey, $id)->delete();
    }

    /**
     * Count rows, optionally for a specific column.
     */
    public static function count(string $column = '*'): int
    {
        return static::query()->count($column);
    }

    /**
     * Check if any rows exist matching the current query.
     */
    public static function exists(): bool
    {
        return static::query()->exists();
    }

    /**
     * Get the sum of a column.
     */
    public static function sum(string $column): int|float|null
    {
        return static::query()->sum($column);
    }

    /**
     * Get the average of a column.
     */
    public static function avg(string $column): int|float|null
    {
        return static::query()->avg($column);
    }

    /**
     * Get the minimum value of a column.
     */
    public static function min(string $column): int|float|string|null
    {
        return static::query()->min($column);
    }

    /**
     * Get the maximum value of a column.
     */
    public static function max(string $column): int|float|string|null
    {
        return static::query()->max($column);
    }

    /**
     * Execute a raw SQL query with parameter bindings.
     * Use with caution: no identifier validation is performed.
     */
    public static function querySql(string $sql, array $bindings = []): array
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