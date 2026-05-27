<?php

declare(strict_types=1);

namespace Arc\Database;

use Arc\Database\Connection;

class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $casts = [];
    private static ?Connection $connection = null;

    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    public static function getConnection(): Connection
    {
        if (static::$connection === null) {
            throw new \RuntimeException('No database connection set. Call Model::setConnection() first.');
        }
        return static::$connection;
    }

    public static function all(): array
    {
        $instance = new static();
        $sql = "SELECT * FROM `{$instance->table}`";
        return static::getConnection()->select($sql);
    }

    public static function find(int|string $id): ?array
    {
        $instance = new static();
        return static::getConnection()->selectOne(
            "SELECT * FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = :id LIMIT 1",
            ['id' => $id],
        );
    }

    public static function where(string $column, mixed $value): array
    {
        $instance = new static();
        return static::getConnection()->select(
            "SELECT * FROM `{$instance->table}` WHERE `{$column}` = :value",
            ['value' => $value],
        );
    }

    public static function create(array $data): int
    {
        $instance = new static();
        $data = $instance->filterFillable($data);

        if (empty($data)) {
            throw new \RuntimeException('No fillable attributes provided.');
        }

        $columns = implode(', ', array_map(fn (string $col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_map(fn (string $col) => ":{$col}", array_keys($data)));

        $sql = "INSERT INTO `{$instance->table}` ({$columns}) VALUES ({$placeholders})";

        return static::getConnection()->insert($sql, $data);
    }

    public static function update(int|string $id, array $data): int
    {
        $instance = new static();
        $data = $instance->filterFillable($data);

        $sets = implode(', ', array_map(fn (string $col) => "`{$col}` = :{$col}", array_keys($data)));
        $data['id'] = $id;

        $sql = "UPDATE `{$instance->table}` SET {$sets} WHERE `{$instance->primaryKey}` = :id";

        return static::getConnection()->update($sql, $data);
    }

    public static function delete(int|string $id): int
    {
        $instance = new static();
        return static::getConnection()->delete(
            "DELETE FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = :id",
            ['id' => $id],
        );
    }

    public static function count(string $column = '*'): int
    {
        $instance = new static();
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