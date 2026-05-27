<?php

declare(strict_types=1);

namespace Arc\Console\Commands;

class MakeModelCommand extends Command
{
    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return 'Create a new model class';
    }

    public function run(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Model name is required.');
            $this->line('Usage: arc make:model User');
            return 1;
        }

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error('Model name must be PascalCase (e.g. User).');
            return 1;
        }

        $namespace = $this->option($args, 'namespace', 'App\\Models');
        $path = $this->resolvePath($name, $namespace);

        if (file_exists($path)) {
            $this->warning("Model already exists: {$path}");
            return 1;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $table = $this->option($args, 'table', $this->defaultTable($name));
        $content = $this->stub($name, $namespace, $table);
        file_put_contents($path, $content);

        $this->success("Model created: {$path}");
        return 0;
    }

    private function resolvePath(string $name, string $namespace): string
    {
        $base = getcwd() . '/app';
        $relative = str_replace('\\', '/', substr($namespace, 4));
        return $base . $relative . '/' . $name . '.php';
    }

    private function defaultTable(string $name): string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        $table = preg_replace('/_model$/', '', $snake);
        return $table . 's';
    }

    private function stub(string $name, string $namespace, string $table): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Arc\Database\Model;

class {$name} extends Model
{
    protected string \$table = '{$table}';

    protected array \$fillable = [];
}

PHP;
    }
}