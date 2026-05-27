<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use Arc\Config\Repository;

class RepositoryTest extends TestCase
{
    public function testGetReturnsValue(): void
    {
        $repo = new Repository(['app' => ['name' => 'Arc', 'debug' => true]]);
        $this->assertSame('Arc', $repo->get('app.name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $repo = new Repository(['app' => ['name' => 'Arc']]);
        $this->assertNull($repo->get('app.missing'));
        $this->assertSame('fallback', $repo->get('app.missing', 'fallback'));
    }

    public function testSetCreatesNestedKeys(): void
    {
        $repo = new Repository();
        $repo->set('app.name', 'Arc');
        $this->assertSame('Arc', $repo->get('app.name'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $repo = new Repository(['app' => ['name' => 'Old']]);
        $repo->set('app.name', 'New');
        $this->assertSame('New', $repo->get('app.name'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $repo = new Repository(['app' => ['name' => 'Arc']]);
        $this->assertTrue($repo->has('app.name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $repo = new Repository(['app' => ['name' => 'Arc']]);
        $this->assertFalse($repo->has('app.missing'));
        $this->assertFalse($repo->has('nonexistent.key'));
    }

    public function testAllReturnsEntireConfig(): void
    {
        $data = ['app' => ['name' => 'Arc'], 'db' => ['host' => 'localhost']];
        $repo = new Repository($data);
        $this->assertSame($data, $repo->all());
    }

    public function testDeepNestedAccess(): void
    {
        $repo = new Repository(['a' => ['b' => ['c' => 'deep']]]);
        $this->assertSame('deep', $repo->get('a.b.c'));
    }
}