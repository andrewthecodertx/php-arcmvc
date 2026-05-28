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
        file_put_contents($this->tmpDir . '/home/index.phtml', '<h1>Hello <?= $name ?></h1>');

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index', ['name' => 'Arc']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello Arc', $response->getContent());
    }

    public function testRenderViewWithLayout(): void
    {
        file_put_contents($this->tmpDir . '/layouts/main.phtml', '<html><?= $this->yield(\'content\') ?></html>');
        file_put_contents($this->tmpDir . '/home/index.phtml', '<?php $this->extend(\'main\') ?><h1>Hello</h1>');

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<html>', $response->getContent());
        $this->assertStringContainsString('<h1>Hello</h1>', $response->getContent());
    }

    public function testYieldContentPassthrough(): void
    {
        file_put_contents($this->tmpDir . '/layouts/main.phtml', '<main><?= $this->yield(\'content\') ?></main>');
        file_put_contents($this->tmpDir . '/home/index.phtml', '<?php $this->extend(\'main\') ?><p>Body</p>');

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<main><p>Body</p></main>', $response->getContent());
    }

    public function testNamedSections(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<title><?= $this->yield(\'title\', \'Default\') ?></title><?= $this->yield(\'content\') ?>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><?php $this->section(\'title\', \'Custom\') ?><p>Body</p>'
        );

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<title>Custom</title>', $response->getContent());
        $this->assertStringContainsString('<p>Body</p>', $response->getContent());
    }

    public function testYieldDefaultWhenSectionNotDefined(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<title><?= $this->yield(\'title\', \'Fallback\') ?></title>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><p>Body</p>'
        );

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<title>Fallback</title>', $response->getContent());
    }

    public function testLayoutNotFoundThrowsException(): void
    {
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'nonexistent\') ?><p>Body</p>'
        );

        $renderer = new Renderer($this->tmpDir);
        $this->expectException(\RuntimeException::class);
        $renderer->render('home.index');
    }

    public function testPartialRender(): void
    {
        file_put_contents($this->tmpDir . '/home/_nav.phtml', '<nav>Menu</nav>');

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

    public function testSetViewsPath(): void
    {
        $renderer = new Renderer('/old');
        $renderer->setViewsPath('/new');
        $this->assertSame('/new', $renderer->getViewsPath());
    }

    public function testPartialFromTemplate(): void
    {
        file_put_contents($this->tmpDir . '/home/_nav.phtml', '<nav><?= $label ?></nav>');
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<?= $this->yield(\'content\') ?>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><?= $this->partial(\'home._nav\', [\'label\' => \'Go\']) ?>'
        );

        $renderer = new Renderer($this->tmpDir);
        $response = $renderer->render('home.index');

        $this->assertStringContainsString('<nav>Go</nav>', $response->getContent());
    }
}