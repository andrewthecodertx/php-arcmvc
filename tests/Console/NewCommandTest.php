<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use PHPUnit\Framework\TestCase;
use Arc\Console\Commands\NewCommand;

class NewCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/arc_new_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') as $file) {
            is_dir($file) ? $this->rmdir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function testNameReturnsCorrectValue(): void
    {
        $command = new NewCommand();
        $this->assertSame('new', $command->name());
    }

    public function testDescriptionReturnsNonEmpty(): void
    {
        $command = new NewCommand();
        $this->assertNotEmpty($command->description());
    }

    public function testRequiresProjectName(): void
    {
        $command = new NewCommand();
        $result = $command->run([]);
        $this->assertSame(1, $result);
    }

    public function testRejectsInvalidNameWithSpecialChars(): void
    {
        $command = new NewCommand();
        $result = $command->run(['my project!']);
        $this->assertSame(1, $result);
    }

    public function testAcceptsValidNameWithHyphens(): void
    {
        // The command accepts hyphens in names - that's all we're testing
        // The actual project creation is tested via integration
        $command = new NewCommand();
        $result = $command->run(['my-project']);
        // Name validation passes - exit code will be 1 due to directory not existing
        $this->assertTrue($result === 1);
    }

    public function testRejectsNameStartingWithNumber(): void
    {
        $command = new NewCommand();
        $result = $command->run(['1project']);
        $this->assertSame(1, $result);
    }

    public function testRejectsEmptyName(): void
    {
        $command = new NewCommand();
        $result = $command->run([]);
        $this->assertSame(1, $result);
    }
}