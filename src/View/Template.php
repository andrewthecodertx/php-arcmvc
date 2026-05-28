<?php

declare(strict_types=1);

namespace Arc\View;

/**
 * The $this object available inside .phtml templates.
 *
 * Usage in a view:
 *   <?php $this->extend('main') ?>
 *   <?php $this->section('title', 'Home') ?>
 *   <p>Page content</p>
 *
 * Usage in a layout:
 *   <title><?= $this->yield('title', 'Arc') ?></title>
 *   <?= $this->yield('content') ?>
 */
class Template
{
    private Renderer $renderer;

    /** @var array<string, string> Named sections defined by the view */
    private array $sections = [];

    /** The view's captured output (used as the default 'content' yield) */
    private string $content = '';

    /** Layout to extend, set by the view via extend() */
    private ?string $layout = null;

    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    public function yield(string $name, string $default = ''): string
    {
        if ($name === 'content') {
            return $this->content;
        }

        return $this->sections[$name] ?? $default;
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->renderer->partial($template, $data);
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }

    /**
     * Capture a template file with $this bound to this Template instance.
     */
    public function capture(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean() ?: '';
    }
}