<?php

declare(strict_types=1);

namespace Tests\View;

use PHPUnit\Framework\TestCase;
use Arc\View\Template;
use Arc\View\Renderer;

class TemplateTest extends TestCase
{
    private string $tmpDir;
    private Renderer $renderer;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/arc_test_template_' . uniqid();
        mkdir($this->tmpDir . '/layouts', 0755, true);
        mkdir($this->tmpDir . '/home', 0755, true);
        $this->renderer = new Renderer($this->tmpDir);
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

    public function testYieldReturnsRawContent(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<?= $this->yield(\'content\') ?>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><h1>Hello</h1>'
        );

        $response = $this->renderer->render('home.index');
        $this->assertStringContainsString('<h1>Hello</h1>', $response->getContent());
    }

    public function testYieldNamedSectionReturnsRawValue(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<?= $this->yield(\'title\') ?><?= $this->yield(\'content\') ?>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><?php $this->section(\'title\', \'My Page\') ?><p>Body</p>'
        );

        $response = $this->renderer->render('home.index');
        $this->assertStringContainsString('My Page', $response->getContent());
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

        $response = $this->renderer->render('home.index');
        $this->assertStringContainsString('<title>Fallback</title>', $response->getContent());
    }

    public function testSectionStoresRawNotEscaped(): void
    {
        $template = new Template($this->renderer);
        $template->section('title', '<b>Bold</b>');
        $raw = $template->getSections()['title'] ?? '';
        $this->assertSame('<b>Bold</b>', $raw);
    }

    public function testEscapeHelperEscapesHtml(): void
    {
        $template = new Template($this->renderer);
        $escaped = $template->e('<script>alert("xss")</script>');
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $escaped
        );
    }

    public function testEscapeHelperPreservesSafeText(): void
    {
        $template = new Template($this->renderer);
        $escaped = $template->e('Hello World');
        $this->assertSame('Hello World', $escaped);
    }

    public function testEscapeHelperHandlesQuotes(): void
    {
        $template = new Template($this->renderer);
        $escaped = $template->e('He said "hello" & \'bye\'');
        $this->assertSame('He said &quot;hello&quot; &amp; &#039;bye&#039;', $escaped);
    }

    public function testYieldContentInLayoutNotDoubleEncoded(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<main><?= $this->yield(\'content\') ?></main>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><p>Hello <em>world</em></p>'
        );

        $response = $this->renderer->render('home.index');
        $this->assertStringContainsString('<main><p>Hello <em>world</em></p></main>', $response->getContent());
        $this->assertStringNotContainsString('&lt;p&gt;', $response->getContent());
    }

    public function testNamedSectionHtmlNotEscapedByYield(): void
    {
        file_put_contents(
            $this->tmpDir . '/layouts/main.phtml',
            '<nav><?= $this->yield(\'nav\') ?></nav>'
        );
        file_put_contents(
            $this->tmpDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><?php $this->section(\'nav\', \'<a href="/">Home</a>\') ?>Content'
        );

        $response = $this->renderer->render('home.index');
        $this->assertStringContainsString('<nav><a href="/">Home</a></nav>', $response->getContent());
        $this->assertStringNotContainsString('&lt;a', $response->getContent());
    }

    public function testCsrfFieldGeneratesHiddenInput(): void
    {
        $template = new Template($this->renderer);
        // Manually call capture to populate data
        $tmpFile = $this->tmpDir . '/home/_csrf.phtml';
        file_put_contents($tmpFile, 'csrf test');
        $template->capture($tmpFile, ['_csrf_token' => 'abc123def456']);
        $html = $template->csrfField();
        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('value="abc123def456"', $html);
    }

    public function testCsrfFieldWithCustomFieldName(): void
    {
        $template = new Template($this->renderer);
        $tmpFile = $this->tmpDir . '/home/_csrf2.phtml';
        file_put_contents($tmpFile, 'csrf test');
        $template->capture($tmpFile, ['_csrf_token' => 'tok']);
        $html = $template->csrfField('custom_csrf');
        $this->assertStringContainsString('name="custom_csrf"', $html);
    }

    public function testCsrfFieldEscapesValue(): void
    {
        $template = new Template($this->renderer);
        $tmpFile = $this->tmpDir . '/home/_csrf3.phtml';
        file_put_contents($tmpFile, 'csrf test');
        $template->capture($tmpFile, ['_csrf_token' => '<script>alert(1)</script>']);
        $html = $template->csrfField();
        $this->assertStringContainsString('value="&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
    }

    public function testGetDataReturnsValueByKey(): void
    {
        $template = new Template($this->renderer);
        $tmpFile = $this->tmpDir . '/home/_data.phtml';
        file_put_contents($tmpFile, 'data test');
        $template->capture($tmpFile, ['name' => 'Alice', 'age' => 30]);
        $this->assertSame('Alice', $template->getData('name'));
        $this->assertSame(30, $template->getData('age'));
    }

    public function testGetDataReturnsDefaultForMissingKey(): void
    {
        $template = new Template($this->renderer);
        $tmpFile = $this->tmpDir . '/home/_data2.phtml';
        file_put_contents($tmpFile, 'data test');
        $template->capture($tmpFile, ['name' => 'Alice']);
        $this->assertNull($template->getData('missing'));
        $this->assertSame('default', $template->getData('missing', 'default'));
    }

    public function testAllDataReturnsEntireDataArray(): void
    {
        $template = new Template($this->renderer);
        $tmpFile = $this->tmpDir . '/home/_data3.phtml';
        file_put_contents($tmpFile, 'data test');
        $data = ['name' => 'Bob', 'role' => 'admin'];
        $template->capture($tmpFile, $data);
        $this->assertSame($data, $template->allData());
    }
}