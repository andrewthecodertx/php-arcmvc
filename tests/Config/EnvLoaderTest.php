<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use Arc\Config\EnvLoader;

class EnvLoaderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/arc_test_env_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            // Clean up env vars we may have set
            $lines = file($this->tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line && !str_starts_with($line, '#') && str_contains($line, '=')) {
                        $key = trim(explode('=', $line, 2)[0]);
                        putenv($key);
                        unset($_ENV[$key], $_SERVER[$key]);
                    }
                }
            }
            unlink($this->tmpFile);
        }
    }

    public function testLoadSetsEnvironmentVariable(): void
    {
        file_put_contents($this->tmpFile, "ARC_TEST_VAR=hello\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('hello', getenv('ARC_TEST_VAR'));
        $this->assertSame('hello', $_ENV['ARC_TEST_VAR']);
    }

    public function testLoadSkipsComments(): void
    {
        file_put_contents($this->tmpFile, "# This is a comment\nARC_TEST_COMMENT=yes\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('yes', getenv('ARC_TEST_COMMENT'));
    }

    public function testLoadSkipsEmptyLines(): void
    {
        file_put_contents($this->tmpFile, "\n\nARC_TEST_EMPTY=val\n\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('val', getenv('ARC_TEST_EMPTY'));
    }

    public function testLoadStripsDoubleQuotes(): void
    {
        file_put_contents($this->tmpFile, 'ARC_TEST_DBLQUOTED="hello world"');
        EnvLoader::load($this->tmpFile);
        $this->assertSame('hello world', getenv('ARC_TEST_DBLQUOTED'));
    }

    public function testLoadStripsSingleQuotes(): void
    {
        file_put_contents($this->tmpFile, "ARC_TEST_SGLQUOTED='hello world'");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('hello world', getenv('ARC_TEST_SGLQUOTED'));
    }

    public function testLoadDoesNotOverwriteExistingEnv(): void
    {
        putenv('ARC_TEST_EXISTING=original');
        file_put_contents($this->tmpFile, "ARC_TEST_EXISTING=overwritten\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('original', getenv('ARC_TEST_EXISTING'));
        putenv('ARC_TEST_EXISTING');
    }

    public function testLoadHandlesEmptyValue(): void
    {
        file_put_contents($this->tmpFile, "ARC_TEST_EMPTY_VAL=\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('', getenv('ARC_TEST_EMPTY_VAL'));
    }

    public function testLoadStripsInlineComments(): void
    {
        file_put_contents($this->tmpFile, "ARC_TEST_INLINE=hello # this is a comment\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('hello', getenv('ARC_TEST_INLINE'));
    }

    public function testLoadHandlesMissingFileGracefully(): void
    {
        // Should not throw
        EnvLoader::load('/nonexistent/path/.env');
        $this->assertTrue(true);
    }

    public function testLoadSkipsLinesWithoutEquals(): void
    {
        file_put_contents($this->tmpFile, "NOT_A_VALID_LINE\nARC_TEST_NOEQUALS=yes\n");
        EnvLoader::load($this->tmpFile);
        $this->assertSame('yes', getenv('ARC_TEST_NOEQUALS'));
    }
}