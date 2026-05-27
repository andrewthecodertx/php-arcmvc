<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Arc\Database\Connection;

class ConnectionTest extends TestCase
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

    public function testMakeCreatesConnection(): void
    {
        $conn = Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->assertInstanceOf(Connection::class, $conn);
    }

    public function testSqliteInMemoryConnection(): void
    {
        $this->skipWithoutSqlite();

        $conn = Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $conn->getPdo();
        $this->assertNotNull($pdo);

        $conn->statement('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->insert('INSERT INTO test (name) VALUES (:name)', ['name' => 'Arc']);
        $result = $conn->selectOne('SELECT * FROM test WHERE name = :name', ['name' => 'Arc']);

        $this->assertSame('Arc', $result['name']);
    }

    public function testSelectReturnsAllRows(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createTestTable();

        $conn->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'A']);
        $conn->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'B']);

        $results = $conn->select('SELECT * FROM items ORDER BY name');
        $this->assertCount(2, $results);
    }

    public function testUpdateReturnsRowCount(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createTestTable();

        $conn->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'Old']);
        $affected = $conn->update('UPDATE items SET name = :name WHERE name = :old', [
            'name' => 'New',
            'old' => 'Old',
        ]);

        $this->assertSame(1, $affected);
    }

    public function testDeleteReturnsRowCount(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createTestTable();

        $conn->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'Gone']);
        $affected = $conn->delete('DELETE FROM items WHERE name = :name', ['name' => 'Gone']);

        $this->assertSame(1, $affected);
    }

    public function testTransactionCommits(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createTestTable();

        $conn->transaction(function (Connection $c) {
            $c->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'Tx1']);
            $c->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'Tx2']);
        });

        $this->assertSame(2, $conn->selectOne('SELECT COUNT(*) as c FROM items')['c']);
    }

    public function testTransactionRollsBackOnException(): void
    {
        $this->skipWithoutSqlite();
        $conn = $this->createTestTable();

        try {
            $conn->transaction(function (Connection $c) {
                $c->insert('INSERT INTO items (name) VALUES (:name)', ['name' => 'WillRollback']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $conn->selectOne('SELECT COUNT(*) as c FROM items')['c']);
    }

    public function testDsnWithPort(): void
    {
        $conn = Connection::make([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'port' => '3307',
            'database' => 'mydb',
            'charset' => 'utf8mb4',
        ]);
        $this->assertInstanceOf(Connection::class, $conn);
    }

    private function createTestTable(): Connection
    {
        $conn = Connection::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $conn->statement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        return $conn;
    }
}