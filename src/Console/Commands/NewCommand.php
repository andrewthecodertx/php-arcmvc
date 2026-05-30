<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

use Arc\Config\EnvLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Create a new Arc project.
 *
 * Usage: arc new <name> [--dir=<path>] [--with-tests]
 *
 * The command creates a new project directory, copies the skeleton files,
 * creates a .env file, and installs Composer dependencies.
 */
class NewCommand extends Command
{
    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Create a new Arc project';
    }

    public function run(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->error('Project name is required.');
            $this->line('');
            $this->line('Usage: arc new my-project [--dir=/path/to/dir] [--with-tests]');
            $this->line('');
            $this->info('Options:');
            $this->line('  --dir=<path>     Directory where project will be created');
            $this->line('  --with-tests     Install phpunit and run initial test setup');
            return 1;
        }

        // Validate project name
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name)) {
            $this->error('Invalid project name.');
            $this->line('');
            $this->line('Project names must:');
            $this->line('  - Start with a letter');
            $this->line('  - Contain only letters, numbers, hyphens, and underscores');
            return 1;
        }

        // Determine project path
        $projectDir = $this->option($args, 'dir');
        $projectDir = $projectDir ? Path::makeAbsolute($projectDir, getcwd()) : getcwd();
        $projectPath = Path::makeAbsolute($projectDir . '/' . $name, getcwd());

        if (file_exists($projectPath)) {
            $this->error("Directory already exists: {$projectPath}");
            return 1;
        }

        // Create project structure
        $this->info("Creating Arc project: {$name}");
        $this->info("  Target: {$projectPath}");
        $this->line('');

        $this->success('  Creating directory structure...');
        $this->createDirectories($projectPath);

        $this->success('  Copying skeleton files...');
        if (!$this->copySkeleton($projectPath)) {
            return 1;
        }

        $this->success('  Configuring project...');
        $this->configureProject($projectPath, $name);

        $this->success('  Creating .env file...');
        $this->createEnvFile($projectPath);

        $this->success('  Installing dependencies...');
        if (!$this->installDependencies($projectPath)) {
            return 1;
        }

        // Optional: Run tests
        if ($this->hasOption($args, 'with-tests')) {
            $this->line('');
            $this->info('  Running initial tests...');
            $this->runTests($projectPath);
        }

        // Done!
        $this->line('');
        $this->success('Project created successfully!');
        $this->line('');
        $this->info('Next steps:');
        $this->line("  cd {$projectPath}");
        $this->line('  php bin/arc serve');
        $this->line('');
        $this->info('Then visit http://localhost:8080');
        $this->line('');
        $this->info('Configure your database in .env before using Model features.');
        $this->line('');

        return 0;
    }

    private function createDirectories(string $path): void
    {
        $dirs = [
            '',
            'app/Controllers',
            'app/Models',
            'bin',
            'config',
            'database',
            'public',
            'resources/views/home',
            'resources/views/layouts',
            'routes',
            'bootstrap',
            'tests',
        ];

        $fs = new Filesystem();
        foreach ($dirs as $dir) {
            $fullPath = $path . '/' . $dir;
            $fs->mkdir($fullPath, 0755);
        }
    }

    private function copySkeleton(string $projectPath): bool
    {
        $arcRoot = dirname(__DIR__, 3);

        // Try multiple skeleton locations:
        $candidates = [
            $arcRoot . '/skeleton',                                           // Dev install
            $arcRoot . '/../../skeleton',                                     // Composer installed package
            realpath($arcRoot . '/../skeleton'),                              // Relative to src
        ];

        $skeletonPath = null;
        foreach ($candidates as $path) {
            if ($path && is_dir($path)) {
                $skeletonPath = $path;
                break;
            }
        }

        if ($skeletonPath === null) {
            $this->error('Skeleton directory not found. Tried:');
            foreach ($candidates as $path) {
                $this->line("  {$path}");
            }
            $this->line('');
            $this->error('Ensure Arc is installed via Composer or run from the framework source.');
            return false;
        }

        $this->recursiveCopy($skeletonPath, $projectPath);
        return true;
    }

    private function configureProject(string $projectPath, string $name): void
    {
        // Replace placeholders in composer.json
        $composerJson = $projectPath . '/composer.json';
        if (file_exists($composerJson)) {
            $content = file_get_contents($composerJson);
            $vendor = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $name));
            $content = str_replace('{{VENDOR}}', $vendor, $content);
            $content = str_replace('{{PROJECT}}', $name, $content);
            file_put_contents($composerJson, $content);
        }

        // Make bin/arc executable
        $binArc = $projectPath . '/bin/arc';
        if (file_exists($binArc)) {
            chmod($binArc, 0755);
        }
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) {
                    mkdir($dstPath, 0755, true);
                }
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private function createEnvFile(string $projectPath): void
    {
        $example = $projectPath . '/.env.example';
        $env = $projectPath . '/.env';

        if (file_exists($example)) {
            copy($example, $env);
        } else {
            // Generate a .env file directly
            file_put_contents($env, $this->getEnvContent());
        }
    }

    private function getEnvContent(): string
    {
        return <<<ENV
# Application settings
APP_NAME=Arc
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database settings
DB_CONNECTION=sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database/arc.sqlite
DB_USERNAME=app_user
DB_PASSWORD=
ENV;
    }

    private function installDependencies(string $projectPath): bool
    {
        $composer = $this->findComposer();
        if ($composer === null) {
            $this->warning('  Composer not found. Skipping dependency installation.');
            $this->info('');
            $this->info('  To complete setup, install Composer and run:');
            $this->info("    cd {$projectPath}");
            $this->info('    composer install');
            return false;
        }

        // Detect dev install: if running from framework source, add a path
        // repository so composer can resolve andrewthecoder/arcmvc locally
        $arcRoot = dirname(__DIR__, 3);
        if (file_exists($arcRoot . '/composer.json')) {
            $arcComposer = json_decode(file_get_contents($arcRoot . '/composer.json'), true);
            if (($arcComposer['name'] ?? '') === 'andrewthecoder/arcmvc') {
                $projectComposer = json_decode(file_get_contents($projectPath . '/composer.json'), true);
                $projectComposer['repositories'] = [
                    [
                        'type' => 'path',
                        'url' => $arcRoot,
                        'options' => ['symlink' => true],
                    ],
                ];
                // Unstable source has no tags, so use dev-main constraint
                $projectComposer['require']['andrewthecoder/arcmvc'] = 'dev-main';
                $projectComposer['minimum-stability'] = 'dev';
                file_put_contents(
                    $projectPath . '/composer.json',
                    json_encode($projectComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
        }

        $process = new Process([$composer, 'install', '--no-interaction'], $projectPath);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('  Composer install failed.');
            $this->line($process->getErrorOutput());
            return false;
        }

        return true;
    }

    private function runTests(string $projectPath): void
    {
        $phpunit = $projectPath . '/vendor/bin/phpunit';
        if (!file_exists($phpunit)) {
            $this->warning('  phpunit not found. Skipping test run.');
            return;
        }

        $process = new Process([$phpunit], $projectPath);
        $process->setTimeout(60);
        $process->run(function ($type, $buffer) {
            if ($type === Process::OUT) {
                echo $buffer;
            }
        });
    }

    private function findComposer(): ?string
    {
        // Check for composer.phar first, then global composer
        if (file_exists('composer.phar')) {
            return 'php composer.phar';
        }

        exec('which composer 2>/dev/null', $output, $exitCode);
        if ($exitCode === 0 && !empty($output[0])) {
            return $output[0];
        }

        // Try composer in PATH
        exec('which composer 2>&1', $output, $exitCode);
        if ($exitCode === 0) {
            return 'composer';
        }

        return null;
    }
}