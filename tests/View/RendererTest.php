<?php

declare(strict_types=1);

namespace Tests\View;

use PHPUnit\Framework\TestCase;
use Arc\View\Renderer;
use Arc\Http\Response;

class RendererTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/arc_test_views_' . uniqid();
        mkdir($this->tmpDir . '/layouts', 0755, true);
        mkdir($this->tmpDir . '/home', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
    }

    private function rmdir(string $dir): void
    {
        foreach (glob($dir . '/*') as $file) {
            is_dir($file) ? $this->rmdir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function testRenderViewWithoutLayout(): void
    {
        file_put_contents($this->tmpDir . '/home/index.php', '<h1>Hello <?= $name ?></h1>');

        $renderer = new Renderer($this->tmpDir);
        $renderer->setLayout('__nonexistent__');
        $response = $renderer->render('home.index', ['name' => 'Arc']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello Arc', $response->getContent());
    }

    public function testRenderViewWithLayout(): void
    {
        file_put_contents($this->tmpDir . '/layouts/main.php', '<html><?= $content ?></html>');
        file_put_contents($this->tmpDir . '/home/index.php', '<h1>Hello</h1>');

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<html>', $response->getContent());
        $this->assertStringContainsString('<h1>Hello</h1>', $response->getContent());
    }

    public function testPartialRender(): void
    {
        file_put_contents($this->tmpDir . '/home/_nav.php', '<nav>Menu</nav>');

        $renderer = new Renderer($this->tmpDir);
        $result = $renderer->partial('home._nav');

        $this->assertStringContainsString('<nav>Menu</nav>', $result);
    }

    public function testMissingViewThrowsException(): void
    {
        $renderer = new Renderer($this->tmpDir);
        $this->expectException(\RuntimeException::class);
        $renderer->render('nonexistent.view');
    }
}