<?php

declare(strict_types=1);

namespace Arc\Database;

/**
 * Fluent SQL query builder.
 *
 * Builds parameterized SELECT, INSERT, UPDATE, and DELETE queries with
 * positional ? bindings. All identifiers (table/column names) are validated
 * against a strict regex to prevent SQL injection.
 *
 * Usage:
 *   $builder = new QueryBuilder($connection, 'users');
 *   $users = $builder->where('active', 1)->orderBy('name')->limit(10)->get();
 */
class QueryBuilder
{
    private array $wheres = [];
    private array $orders = [];
    private array $columns = ['*'];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $bindings = [];
    private string $table;

    public function __construct(
        private Connection $connection,
        string $table,
    ) {
        self::assertValidIdentifier($table, 'table name');
        $this->table = $table;
    }

    // --- Fluent methods ---

    public function select(array $columns = ['*']): self
    {
        foreach ($columns as $col) {
            if ($col !== '*') {
                self::assertValidIdentifier($col, 'column name');
            }
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * Two-arg form: where('col', $value) → col = ?
     * Three-arg form: where('col', '>', $value) → col > ?
     * Null value in two-arg form: where('col', null) → col IS NULL
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        self::assertValidIdentifier($column, 'column name');

        if ($value === null) {
            if ($operatorOrValue === null) {
                $this->wheres[] = "`{$column}` IS NULL";
            } else {
                $this->wheres[] = "`{$column}` = ?";
                $this->bindings[] = $operatorOrValue;
            }
        } else {
            $operator = strtoupper(trim((string) $operatorOrValue));
            $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
            if (!in_array($operator, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid SQL operator: {$operatorOrValue}");
            }
            $this->wheres[] = "`{$column}` {$operator} ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        self::assertValidIdentifier($column, 'column name');
        $this->wheres[] = "`{$column}` IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        self::assertValidIdentifier($column, 'column name');
        $this->wheres[] = "`{$column}` IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        self::assertValidIdentifier($column, 'column name');

        if (empty($values)) {
            $this->wheres[] = '0 = 1';
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "`{$column}` IN ({$placeholders})";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        self::assertValidIdentifier($column, 'column name');

        if (empty($values)) {
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "`{$column}` NOT IN ({$placeholders})";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        self::assertValidIdentifier($column, 'column name');
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid sort direction: {$direction}. Use 'asc' or 'desc'.");
        }

        $this->orders[] = "`{$column}` {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);
        return $this;
    }

    // --- Terminal methods ---

    public function get(): array
    {
        return $this->connection->select($this->buildSelect(), $this->bindings);
    }

    public function first(): ?array
    {
        $this->limitValue = 1;
        return $this->connection->selectOne($this->buildSelect(), $this->bindings);
    }

    public function count(string $column = '*'): int
    {
        if ($column !== '*') {
            self::assertValidIdentifier($column, 'column name');
        }

        $sql = "SELECT COUNT({$column}) AS aggregate FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $result = $this->connection->selectOne($sql, $this->bindings);
        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): int|float|null
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column): int|float|null
    {
        return $this->aggregate('AVG', $column);
    }

    public function min(string $column): int|float|string|null
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): int|float|string|null
    {
        return $this->aggregate('MAX', $column);
    }

    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \RuntimeException('No data provided for insert.');
        }

        foreach (array_keys($data) as $col) {
            self::assertValidIdentifier($col, 'column name');
        }

        $columns = implode(', ', array_map(fn (string $col): string => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";

        return $this->connection->insert($sql, array_values($data));
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            throw new \RuntimeException('No data provided for update.');
        }

        foreach (array_keys($data) as $col) {
            self::assertValidIdentifier($col, 'column name');
        }

        $sets = implode(', ', array_map(fn (string $col): string => "`{$col}` = ?", array_keys($data)));
        $bindings = array_values($data);
        $sql = "UPDATE `{$this->table}` SET {$sets}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`";
        $bindings = $this->bindings;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->connection->delete($sql, $bindings);
    }

    // --- Internal ---

    private function buildSelect(): string
    {
        $cols = implode(', ', array_map(function (string $col): string {
            return $col === '*' ? '*' : "`{$col}`";
        }, $this->columns));

        $sql = "SELECT {$cols} FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    private function aggregate(string $function, string $column): mixed
    {
        self::assertValidIdentifier($column, 'column name');

        $sql = "SELECT {$function}(`{$column}`) AS aggregate FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $result = $this->connection->selectOne($sql, $this->bindings);
        return $result['aggregate'] ?? null;
    }

    public static function assertValidIdentifier(string $identifier, string $context): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier in {$context}: {$identifier}");
        }
    }
}