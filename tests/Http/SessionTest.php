<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Arc\Http\Session;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
        // Ensure clean state
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testSetAndGet(): void
    {
        $this->session->set('name', 'Arc');
        $this->assertSame('Arc', $this->session->get('name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('default', $this->session->get('missing', 'default'));
        $this->assertNull($this->session->get('missing'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->session->has('key'));
        $this->session->set('key', 'value');
        $this->assertTrue($this->session->has('key'));
    }

    public function testRemove(): void
    {
        $this->session->set('key', 'value');
        $this->assertTrue($this->session->has('key'));
        $this->session->remove('key');
        $this->assertFalse($this->session->has('key'));
    }

    public function testAll(): void
    {
        $this->session->set('a', 1);
        $this->session->set('b', 2);
        $all = $this->session->all();
        $this->assertSame(1, $all['a']);
        $this->assertSame(2, $all['b']);
    }

    public function testFlashSetsAndRetrieves(): void
    {
        $this->session->setFlash('status', 'success');
        $this->assertTrue($this->session->hasFlash('status'));

        $value = $this->session->flash('status');
        $this->assertSame('success', $value);

        // Flash should be removed after reading
        $this->assertFalse($this->session->hasFlash('status'));
        $this->assertSame('default', $this->session->flash('status', 'default'));
    }

    public function testFlashReturnsDefaultWhenNotSet(): void
    {
        $this->assertNull($this->session->flash('missing'));
        $this->assertSame('fallback', $this->session->flash('missing', 'fallback'));
    }

    public function testIsStarted(): void
    {
        $session = new Session();
        $this->assertFalse($session->isStarted());
        $session->set('key', 'value');
        $this->assertTrue($session->isStarted());
    }

    public function testDestroy(): void
    {
        $this->session->set('key', 'value');
        $this->session->destroy();
        $this->assertFalse($this->session->isStarted());
    }

    public function testGetId(): void
    {
        $this->session->set('key', 'value');
        $id = $this->session->getId();
        $this->assertNotEmpty($id);
    }

    public function testRegenerate(): void
    {
        $this->session->set('key', 'value');
        $oldId = $this->session->getId();
        $this->session->regenerate();
        $newId = $this->session->getId();
        $this->assertNotSame($oldId, $newId);
        // Data should persist after regeneration
        $this->assertSame('value', $this->session->get('key'));
    }
}