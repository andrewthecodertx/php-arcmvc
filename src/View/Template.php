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

    /** View data (passed from renderer, available for helpers like csrf) */
    private array $data = [];

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

    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
     *
     * View data is extracted into local variables via extract(EXTR_SKIP)
     * for template convenience: `<?= $name ?>`. This means template data keys
     * can collide with local variables ($path, $data, $this, etc.).
     *
     * For explicit, collision-free access, use $this->getData('key') or
     * $this->allData() instead of relying on extracted variables.
     */
    public function capture(string $path, array $data): string
    {
        $this->data = $data;
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean() ?: '';
    }

    /**
     * Get a value from the view data by key (explicit access, no extract collision).
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get all view data (explicit access, no extract collision).
     */
    public function allData(): array
    {
        return $this->data;
    }

    /**
     * Render a CSRF token hidden input field for forms.
     * Requires '_csrf_token' to be present in the view data
     * (set by CsrfMiddleware via Request attribute).
     */
    public function csrfField(string $fieldName = '_token'): string
    {
        $token = $this->data['_csrf_token'] ?? '';
        $escaped = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = htmlspecialchars($fieldName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $escaped . '">';
    }
}

