<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Arc\Database\Model;
use Arc\Database\Connection;
use Arc\Database\QueryBuilder;

class TestModel extends Model
{
    protected string $table = 'test_items';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email'];
}

class ModelWithBadTable extends Model
{
    protected string $table = 'DROP TABLE users;--';
}

class ModelWithBadPrimaryKey extends Model
{
    protected string $table = 'items';
    protected string $primaryKey = '1=1 OR id';
}

class ModelTest extends TestCase
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
        $conn->statement('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT, email TEXT, active INTEGER DEFAULT 1, price REAL, category TEXT)');
        return $conn;
    }

    private function createMockConnection(): Connection
    {
        return Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    // --- SQL Injection Prevention Tests ---

    public function testWhereRejectsInvalidColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        TestModel::setConnection($this->createMockConnection());
        TestModel::where('1=1 OR 1', 'anything');
    }

    public function testWhereRejectsColumnNameWithBacktick(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestModel::setConnection($this->createMockConnection());
        TestModel::where('name` = 1 OR `1', 'anything');
    }

    public function testAllRejectsInvalidTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        ModelWithBadTable::setConnection($this->createMockConnection());
        ModelWithBadTable::all();
    }

    public function testFindRejectsInvalidPrimaryKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        ModelWithBadPrimaryKey::setConnection($this->createMockConnection());
        ModelWithBadPrimaryKey::find(1);
    }

    public function testCountRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        TestModel::setConnection($this->createMockConnection());
        TestModel::count('1=1');
    }

    public function testDeleteRejectsInvalidTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModelWithBadTable::setConnection($this->createMockConnection());
        ModelWithBadTable::delete(1);
    }

    public function testCreateRejectsInvalidColumnKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $model = new class extends Model {
            protected string $table = 'items';
            protected array $fillable = [];
        };
        $model::setConnection($this->createMockConnection());
        $model::create(['name' => 'ok', 'bad column; DROP TABLE users' => 'evil']);
    }

    public function testUpdateRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $model = new class extends Model {
            protected string $table = 'items';
            protected string $primaryKey = 'id';
            protected array $fillable = [];
        };
        $model::setConnection($this->createMockConnection());
        $model::update(1, ['bad col' => 'evil']);
    }

    // --- Functional tests ---

    public function testWhereAcceptsValidColumnName(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        TestModel::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $results = TestModel::where('name', 'Alice');
        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]['name']);
    }

    public function testCreateWithFillableFiltersInvalidKeys(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        $id = TestModel::create(['name' => 'Bob', 'bad_key' => 'ignored']);
        $this->assertGreaterThan(0, $id);
    }

    public function testCountAcceptsAsterisk(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        $count = TestModel::count('*');
        $this->assertSame(0, $count);
    }

    public function testCreateAndFind(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        TestModel::create(['name' => 'Charlie', 'email' => 'charlie@test.com']);
        $result = TestModel::find(1);

        $this->assertNotNull($result);
        $this->assertSame('Charlie', $result['name']);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        $result = TestModel::find(999);
        $this->assertNull($result);
    }

    public function testFindOrFailReturnsRowWhenFound(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        TestModel::create(['name' => 'Dana', 'email' => 'dana@test.com']);
        $result = TestModel::findOrFail(1);

        $this->assertSame('Dana', $result['name']);
    }

    public function testFindOrFailThrowsWhenNotFound(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        $this->expectException(\RuntimeException::class);
        TestModel::findOrFail(999);
    }

    public function testAllAppliesLimitAndOffset(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        foreach (['a', 'b', 'c', 'd'] as $name) {
            TestModel::create(['name' => $name, 'email' => "{$name}@test.com"]);
        }

        $page = TestModel::all(limit: 2, offset: 1);
        $this->assertCount(2, $page);
        $this->assertSame('b', $page[0]['name']);
        $this->assertSame('c', $page[1]['name']);
    }

    public function testUpdatePersistsFillableValues(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        $id = TestModel::create(['name' => 'Original', 'email' => 'orig@test.com']);

        $affected = TestModel::update($id, ['name' => 'Renamed']);
        $this->assertSame(1, $affected);

        $row = TestModel::find($id);
        $this->assertSame('Renamed', $row['name']);
    }

    public function testQueryReturnsBuilderForChaining(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        TestModel::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        TestModel::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $results = TestModel::query()->where('name', 'Alice')->get();
        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]['name']);
    }

    public function testAggregates(): void
    {
        $this->skipWithoutSqlite();

        $conn = $this->createConnection();
        $conn->statement("INSERT INTO test_items (name, email, price) VALUES ('A', 'a@t.com', 10.0)");
        $conn->statement("INSERT INTO test_items (name, email, price) VALUES ('B', 'b@t.com', 20.0)");
        $conn->statement("INSERT INTO test_items (name, email, price) VALUES ('C', 'c@t.com', 30.0)");

        $model = new class extends Model {
            protected string $table = 'test_items';
            protected string $primaryKey = 'id';
            protected array $fillable = ['name', 'email', 'price'];
        };
        $model::setConnection($conn);

        $this->assertSame(3, $model::count());
        $this->assertEqualsWithDelta(60.0, $model::sum('price'), 0.001);
        $this->assertEqualsWithDelta(20.0, $model::avg('price'), 0.001);
        $this->assertEqualsWithDelta(10.0, $model::min('price'), 0.001);
        $this->assertEqualsWithDelta(30.0, $model::max('price'), 0.001);
    }

    public function testExistsReturnsCorrectBoolean(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());

        $this->assertFalse(TestModel::exists());

        TestModel::create(['name' => 'Eve', 'email' => 'eve@test.com']);
        $this->assertTrue(TestModel::exists());
    }

    public function testQuerySqlForRawQueries(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        TestModel::create(['name' => 'Francis', 'email' => 'fran@test.com']);

        $rows = TestModel::querySql('SELECT * FROM test_items WHERE name LIKE ?', ['%anci%']);
        $this->assertCount(1, $rows);
        $this->assertSame('Francis', $rows[0]['name']);
    }
}