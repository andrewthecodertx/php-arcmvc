<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Create a new Arc project.
 *
 * Usage: arc new <name> [--dir=<path>] [--type=dev|release]
 */
class MakeProjectCommand extends Command
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
        $name = $this->option($args, 'name');
        if ($name === null) {
            $this->error('Usage: arc new <project-name> [--dir=<path>] [--type=dev|release]');
            $this->line('Options:');
            $this->line('  --dir=<path>   Directory where project will be created (default: current)');
            $this->line('  --type=dev     Use development version (from local source)');
            $this->line('  --type=release Use stable version from Packagist (default)');
            return 1;
        }

        $targetDir = $this->option($args, 'dir', getcwd());
        $projectDir = Path::makeAbsolute($targetDir . '/' . $name, getcwd());

        if (file_exists($projectDir)) {
            $this->error("Directory already exists: {$projectDir}");
            return 1;
        }

        $type = $this->option($args, 'type', 'release');
        if (!in_array($type, ['dev', 'release'], true)) {
            $this->error("Invalid type '{$type}'. Use 'dev' or 'release'.");
            return 1;
        }

        $this->line("Creating new Arc project: {$name}");
        $this->line("Target directory: {$projectDir}");

        // Create project directory structure
        $this->createDirectoryStructure($projectDir);

        // Copy skeleton files
        $this->copySkeleton($projectDir);

        // Create .env file
        $this->createEnvFile($projectDir);

        // Create .gitignore
        $this->createGitignore($projectDir);

        // Initialize Composer project
        $this->info("Initializing Composer project...");
        if (!$this->initComposer($projectDir, $name, $type)) {
            return 1;
        }

        // Install dependencies
        $this->info("Installing dependencies...");
        if (!$this->installDependencies($projectDir)) {
            return 1;
        }

        $this->success("Project Created Successfully!");
        $this->line("\nNext steps:");
        $this->line("  cd {$projectDir}");
        $this->line("  composer run-script serve");
        $this->line("\nDocumentation: https://andrewthecoder.com/projects/arcmvc");
        return 0;
    }

    private function createDirectoryStructure(string $projectDir): void
    {
        $fs = new Filesystem();
        $dirs = [
            $projectDir . '/app/Controllers',
            $projectDir . '/app/Models',
            $projectDir . '/config',
            $projectDir . '/database',
            $projectDir . '/resources/views/layouts',
            $projectDir . '/resources/views/home',
            $projectDir . '/public',
            $projectDir . '/routes',
            $projectDir . '/bootstrap',
            $projectDir . '/vendor', // Will be created by Composer
        ];

        foreach ($dirs as $dir) {
            $fs->mkdir($dir);
        }
    }

    private function copySkeleton(string $projectDir): void
    {
        $skeletonDir = __DIR__ . '/../../skeleton/public';
        $bootstrapDir = __DIR__ . '/../../skeleton/bootstrap';
        $configDir = __DIR__ . '/../../skeleton/config';
        $routesDir = __DIR__ . '/../../skeleton/routes';
        $appDir = __DIR__ . '/../../skeleton/app';
        $viewsDir = __DIR__ . '/../../skeleton/resources/views';

        // Copy public files
        $this->copyRecursive($skeletonDir, $projectDir . '/public');
        $this->copyRecursive($bootstrapDir, $projectDir . '/bootstrap');
        $this->copyRecursive($configDir, $projectDir . '/config');
        $this->copyRecursive($routesDir, $projectDir . '/routes');
        $this->copyRecursive($appDir, $projectDir . '/app');
        $this->copyRecursive($viewsDir, $projectDir . '/resources/views');

        // Create index.php if not present
        $indexFile = $projectDir . '/public/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, <<<PHP
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\$_ENV['APP_ENV'] = \$_ENV['APP_ENV'] ?? 'production';
\$_ENV['APP_DEBUG'] = \$_ENV['APP_DEBUG'] ?? (\$_ENV['APP_ENV'] === 'local');
\$_ENV['APP_NAME'] = \$_ENV['APP_NAME'] ?? 'Arc';

\$_ENV['DB_CONNECTION'] = \$_ENV['DB_CONNECTION'] ?? 'sqlite';
\$_ENV['DB_HOST'] = \$_ENV['DB_HOST'] ?? '127.0.0.1';
\$_ENV['DB_PORT'] = \$_ENV['DB_PORT'] ?? '3306';
\$_ENV['DB_DATABASE'] = \$_ENV['DB_DATABASE'] ?? 'arc';
\$_ENV['DB_USERNAME'] = \$_ENV['DB_USERNAME'] ?? 'app_user';
\$_ENV['DB_PASSWORD'] = \$_ENV['DB_PASSWORD'] ?? '';

\$app = require __DIR__ . '/../bootstrap/app.php';
\$app->boot();
\$app->run();
PHP
            );
        }
    }

    private function createEnvFile(string $projectDir): void
    {
        $envContent = "# Application settings
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
";
        file_put_contents($projectDir . '/.env', $envContent);
    }

    private function createGitignore(string $projectDir): void
    {
        $gitignore = <<<'GITIGNORE'
# Composer
vendor/

# Environment
.env
.env.local
.env.*.local

# Build artifacts
build/
dist/
*.phar

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# Logs
storage/logs/
*.log

# OS
.DS_Store
Thumbs.db
GITIGNORE;

        file_put_contents($projectDir . '/.gitignore', $gitignore);
    }

    private function initComposer(string $projectDir, string $name, string $type): bool
    {
        $fs = new Filesystem();

        // Create composer.json
        $composer = [
            'name' => "arcmvc/{$name}",
            'description' => "Your Arc-based application",
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '>=8.4',
                'ext-pdo' => '*',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^12',
            ],
            'autoload' => [
                'psr-4' => [
                    "App\\" => "app/",
                    "Arc\\" => "src/",
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    "Tests\\" => "tests/",
                ],
            ],
            'repositories' => [],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        if ($type === 'dev') {
            // Reference local Arc package
            $arcmvcPath = Path::makeRelative(dirname(__DIR__, 2), $projectDir);
            $composer['repositories'] = [
                [
                    'type' => 'path',
                    'url' => $arcmvcPath,
                    'options' => [
                        'symlink' => true,
                    ],
                ],
            ];
            $composer['require']['andrewthecoder/arcmvc'] = 'dev-main';
        }

        file_put_contents($projectDir . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }

    private function installDependencies(string $projectDir): bool
    {
        $phpFinder = new PhpExecutableFinder();
        $php = $phpFinder->find();
        if ($php === false) {
            $this->error('Could not locate PHP executable');
            return false;
        }

        // Change to project directory
        chdir($projectDir);

        // Check if composer is available
        if (!file_exists($projectDir . '/composer.phar') && !($this->which('composer') !== null)) {
            $this->error(' Composer is not installed. Please install Composer first.');
            return false;
        }

        // Use composer.phar if available, otherwise system composer
        $composerBin = $projectDir . '/composer.phar';
        $composerCmd = file_exists($composerBin) ? $php . ' ' . $composerBin : 'composer';

        // Run composer install
        $process = new Process($composerCmd . ' install', $projectDir, null, null, 120);
        $process->run(function ($type, $line) {
            if ($type === Process::OUT) {
                echo $line;
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('Composer install failed');
            $this->error($process->getErrorOutput());
            return false;
        }

        return true;
    }

    private function copyRecursive(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        $fs = new Filesystem();
        $fs->mkdir($destination);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            if ($item->isDir()) {
                $fs->mkdir($dest);
            } else {
                $fs->copy($item->getPathname(), $dest);
            }
        }
    }

    private function which(string $command): ?string
    {
        if ('\\' === DIRECTORY_SEPARATOR && $this->isWindowsExecutable($command)) {
            return $command;
        }

        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '/usr/bin:/usr/local/bin');
        foreach ($paths as $path) {
            if (is_executable($path . '/' . $command)) {
                return $command;
            }
        }

        return null;
    }

    private function isWindowsExecutable(string $command): bool
    {
        if (file_exists($command)) {
            return is_executable($command);
        }

        foreach (['.exe', '.bat', '.cmd', '.ps1'] as $ext) {
            if (file_exists($command . $ext)) {
                return is_executable($command . $ext);
            }
        }

        return false;
    }
}