<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Arc\Database\Connection;
use Arc\Database\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private static bool $sqliteAvailable;

    public static function setUpBeforeClass(): void
    {
        self::$sqliteAvailable = in_array('sqlite', \PDO::getAvailableDrivers());
    }

    private function skipWithoutSqlite(): void
    {
        if (!self::$sqliteAvailable) {
            $this->markTestSkipped('PDO SQLite driver not available');
        }
    }

    private function createConnection(): Connection
    {
        $conn = Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $conn->statement(
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, price REAL, active INTEGER DEFAULT 1, category TEXT)'
        );
        return $conn;
    }

    private function seedItems(Connection $conn): void
    {
        $conn->statement("INSERT INTO items (name, price, active, category) VALUES ('Alpha', 10.0, 1, 'A')");
        $conn->statement("INSERT INTO items (name, price, active, category) VALUES ('Beta', 20.0, 1, 'A')");
        $conn->statement("INSERT INTO items (name, price, active, category) VALUES ('Gamma', 30.0, 0, 'B')");
        $conn->statement("INSERT INTO items (name, price, active, category) VALUES ('Delta', 40.0, 0, 'B')");
        $conn->statement("INSERT INTO items (name, price, active, category) VALUES ('Epsilon', NULL, 1, NULL)");
    }

    // --- Identifier validation ---

    public function testInvalidTableNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        new QueryBuilder($conn, 'DROP TABLE users;--');
    }

    public function testInvalidColumnInWhereThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->where('1=1 OR 1', 'value');
    }

    public function testInvalidColumnInOrderByThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->orderBy('name; DROP TABLE users');
    }

    public function testInvalidDirectionInOrderByThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->orderBy('name', 'sideways');
    }

    public function testInvalidOperatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL operator');
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->where('name', 'EXPLODE', 'value');
    }

    public function testInvalidColumnInSelectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->select(['bad column; drop']);
    }

    // --- SELECT / WHERE ---

    public function testGetReturnsAllRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->get();
        $this->assertCount(5, $rows);
    }

    public function testWhereEqualityFiltersRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->where('category', 'A')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Alpha', $rows[0]['name']);
        $this->assertSame('Beta', $rows[1]['name']);
    }

    public function testWhereWithOperator(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->where('price', '>', 25.0)->get();
        $this->assertCount(2, $rows);
    }

    public function testWhereNullFiltersNulls(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereNull('category')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Epsilon', $rows[0]['name']);
    }

    public function testWhereNotNullFiltersNonNulls(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereNotNull('category')->get();
        $this->assertCount(4, $rows);
    }

    public function testWhereInFiltersByList(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereIn('name', ['Alpha', 'Gamma'])->get();
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Alpha', $names);
        $this->assertContains('Gamma', $names);
    }

    public function testWhereInWithEmptyArrayReturnsNoRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereIn('name', [])->get();
        $this->assertCount(0, $rows);
    }

    public function testWhereNotInExcludesValues(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereNotIn('name', ['Alpha', 'Gamma'])->get();
        $this->assertCount(3, $rows);
    }

    public function testWhereNotInWithEmptyArrayReturnsAll(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereNotIn('name', [])->get();
        $this->assertCount(5, $rows);
    }

    public function testMultipleWheresAreAnded(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->where('active', 1)->where('category', 'A')->get();
        $this->assertCount(2, $rows);
    }

    public function testWhereWithNullValueGeneratesIsNull(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->where('category', null)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Epsilon', $rows[0]['name']);
    }

    // --- ORDER BY / LIMIT / OFFSET ---

    public function testOrderBySortsResults(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->orderBy('price', 'desc')->get();
        $this->assertSame('Delta', $rows[0]['name']);
        $this->assertSame('Alpha', $rows[3]['name']);
    }

    public function testLimitAndOffset(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->whereNotNull('price')->orderBy('price')->limit(2)->offset(1)->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Beta', $rows[0]['name']);
        $this->assertSame('Gamma', $rows[1]['name']);
    }

    public function testFirstReturnsSingleRow(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $row = $qb->where('name', 'Alpha')->first();
        $this->assertNotNull($row);
        $this->assertSame('Alpha', $row['name']);
    }

    public function testFirstReturnsNullWhenNotFound(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $qb = new QueryBuilder($conn, 'items');

        $row = $qb->where('name', 'Nonexistent')->first();
        $this->assertNull($row);
    }

    // --- SELECT specific columns ---

    public function testSelectSpecificColumns(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb->select(['name', 'price'])->orderBy('name')->limit(1)->get();
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayNotHasKey('id', $rows[0]);
    }

    // --- COUNT / EXISTS / AGGREGATES ---

    public function testCountAllRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertSame(5, $qb->count());
    }

    public function testCountWithWhereClause(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertSame(3, $qb->where('active', 1)->count());
    }

    public function testCountSpecificColumn(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertSame(4, $qb->count('price'));
    }

    public function testExistsReturnsTrueWhenRowsFound(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertTrue($qb->where('name', 'Alpha')->exists());
    }

    public function testExistsReturnsFalseWhenNoRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $qb = new QueryBuilder($conn, 'items');

        $this->assertFalse($qb->where('name', 'Nonexistent')->exists());
    }

    public function testSum(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertEqualsWithDelta(100.0, $qb->sum('price'), 0.001);
    }

    public function testAvg(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $avg = $qb->avg('price');
        // 4 non-null prices: 10 + 20 + 30 + 40 = 100 / 4 = 25
        $this->assertEqualsWithDelta(25.0, $avg, 0.001);
    }

    public function testMinAndMax(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertEqualsWithDelta(10.0, $qb->min('price'), 0.001);
        $this->assertEqualsWithDelta(40.0, $qb->max('price'), 0.001);
    }

    public function testSumWithWhereClause(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $this->assertEqualsWithDelta(30.0, $qb->where('category', 'A')->sum('price'), 0.001);
    }

    // --- INSERT ---

    public function testInsertReturnsId(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $qb = new QueryBuilder($conn, 'items');

        $id = $qb->insert(['name' => 'New', 'price' => 50.0, 'active' => 1, 'category' => 'C']);
        $this->assertGreaterThan(0, $id);

        $row = $qb->where('name', 'New')->first();
        $this->assertNotNull($row);
        $this->assertSame('New', $row['name']);
    }

    public function testInsertWithEmptyDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->insert([]);
    }

    public function testInsertWithInvalidColumnThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->insert(['bad column' => 'evil']);
    }

    // --- UPDATE ---

    public function testUpdateWithWhere(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $affected = $qb->where('name', 'Alpha')->update(['price' => 99.0]);
        $this->assertSame(1, $affected);

        $row = (new QueryBuilder($conn, 'items'))->where('name', 'Alpha')->first();
        $this->assertEqualsWithDelta(99.0, $row['price'], 0.001);
    }

    public function testUpdateWithEmptyDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $qb = new QueryBuilder($conn, 'items');
        $qb->update([]);
    }

    // --- DELETE ---

    public function testDeleteWithWhere(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $affected = $qb->where('name', 'Alpha')->delete();
        $this->assertSame(1, $affected);

        $remaining = (new QueryBuilder($conn, 'items'))->count();
        $this->assertSame(4, $remaining);
    }

    // --- Chaining ---

    public function testChainedQuery(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $rows = $qb
            ->where('active', 1)
            ->whereNotNull('category')
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame('Alpha', $rows[0]['name']);
        $this->assertSame('Beta', $rows[1]['name']);
    }

    public function testChainedCountWithWhere(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createConnection();
        $this->seedItems($conn);
        $qb = new QueryBuilder($conn, 'items');

        $count = $qb->where('active', 0)->where('category', 'B')->count();
        $this->assertSame(2, $count);
    }
}