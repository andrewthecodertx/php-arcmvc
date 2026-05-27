<?php

declare(strict_types=1);

namespace Arc\View;

use Arc\Http\Response;

class Renderer
{
    private string $viewsPath;
    private string $layout = 'main';

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? '';
    }

    public function setViewsPath(string $path): self
    {
        $this->viewsPath = $path;
        return $this;
    }

    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    public function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function render(string $template, array $data = []): Response
    {
        $viewFile = $this->resolvePath($template);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$template} (looked in {$viewFile})");
        }

        $content = $this->capture($viewFile, $data);

        $layoutFile = $this->resolvePath("layouts/{$this->layout}");

        if (file_exists($layoutFile)) {
            $data['content'] = $content;
            $content = $this->capture($layoutFile, $data);
        }

        return new Response($content, 200, ['Content-Type' => 'text/html']);
    }

    public function partial(string $template, array $data = []): string
    {
        $viewFile = $this->resolvePath($template);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Partial not found: {$template} (looked in {$viewFile})");
        }

        return $this->capture($viewFile, $data);
    }

    private function capture(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean() ?: '';
    }

    private function resolvePath(string $template): string
    {
        return rtrim($this->viewsPath, '/') . '/' . str_replace('.', '/', $template) . '.php';
    }
}