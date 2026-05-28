<?php

declare(strict_types=1);

namespace Arc\View;

use Arc\Http\Response;

class Renderer
{
    private string $viewsPath;

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

    public function render(string $template, array $data = []): Response
    {
        $viewFile = $this->resolvePath($template);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$template} (looked in {$viewFile})");
        }

        $view = new Template($this);
        $content = $view->capture($viewFile, $data);
        $view->setContent($content);

        if ($view->getLayout() !== null) {
            $layoutFile = $this->resolvePath('layouts/' . $view->getLayout());

            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout not found: {$view->getLayout()} (looked in {$layoutFile})");
            }

            $content = $view->capture($layoutFile, $data);
        }

        return new Response($content, 200, ['Content-Type' => 'text/html']);
    }

    public function partial(string $template, array $data = []): string
    {
        $viewFile = $this->resolvePath($template);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Partial not found: {$template} (looked in {$viewFile})");
        }

        $partial = new Template($this);
        return $partial->capture($viewFile, $data);
    }

    public function resolvePath(string $template): string
    {
        return rtrim($this->viewsPath, '/') . '/' . str_replace('.', '/', $template) . '.phtml';
    }
}