<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

class MakeControllerCommand extends Command
{
    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Create a new controller class';
    }

    public function run(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Controller name is required.');
            $this->line('Usage: arc make:controller HomeController');
            return 1;
        }

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error('Controller name must be PascalCase (e.g. HomeController).');
            return 1;
        }

        $namespace = $this->option($args, 'namespace', 'App\\Controllers');
        $path = $this->resolvePath($name, $namespace);

        if (file_exists($path)) {
            $this->warning("Controller already exists: {$path}");
            return 1;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = $this->stub($name, $namespace);
        file_put_contents($path, $content);

        $this->success("Controller created: {$path}");
        return 0;
    }

    private function resolvePath(string $name, string $namespace): string
    {
        $base = getcwd() . '/app';
        $relative = str_replace('\\', '/', substr($namespace, 4));
        return $base . $relative . '/' . $name . '.php';
    }

    private function stub(string $name, string $namespace): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Arc\Support\Controller;
use Arc\Http\Request;
use Arc\Http\Response;

class {$name} extends Controller
{
    public function index(Request \$request): Response
    {
        return \$this->view('{$this->viewName($name)}.index');
    }
}

PHP;
    }

    private function viewName(string $name): string
    {
        $view = preg_replace('/Controller$/', '', $name);
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $view));
    }
}