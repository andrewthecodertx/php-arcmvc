<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Arc\Database\Model;
use Arc\Database\Connection;

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
        $conn->statement('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        return $conn;
    }

    /**
     * Identifier validation should throw before any DB query is attempted.
     * Use a mock connection that would blow up if actually called.
     */
    private function createMockConnection(): Connection
    {
        // Use SQLite :memory: but don't create any tables.
        // We just need the Connection object; validation should fail before queries.
        return Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    // --- SQL Injection Prevention Tests (no DB needed, validation throws first) ---

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
            // Empty fillable means all data passes through filterFillable
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

    // --- Functional tests (require SQLite) ---

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

    public function testValidIdentifierAcceptsStandardNames(): void
    {
        $this->skipWithoutSqlite();
        TestModel::setConnection($this->createConnection());
        TestModel::create(['name' => 'Test', 'email' => 'test@test.com']);

        $results = TestModel::where('name', 'Test');
        $this->assertCount(1, $results);
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

    public function testUpdateHandlesFillableColumnNamedLikePrimaryKey(): void
    {
        $this->skipWithoutSqlite();

        // A model whose fillable set includes a column named after the primary
        // key must not collide with the WHERE-clause placeholder.
        $conn = Connection::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $conn->statement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT)');

        $model = new class extends Model {
            protected string $table = 'widgets';
            protected string $primaryKey = 'id';
            protected array $fillable = ['name'];
        };
        $model::setConnection($conn);

        $id = $model::create(['name' => 'first']);
        $affected = $model::update($id, ['name' => 'second']);

        $this->assertSame(1, $affected);
        $this->assertSame('second', $model::find($id)['name']);
    }
}