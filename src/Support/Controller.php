<?php

declare(strict_types=1);

namespace Arc\Support;

use Arc\Http\Middleware\CsrfMiddleware;
use Arc\Http\Request;
use Arc\Http\Response;
use Arc\View\Renderer;

abstract class Controller
{
    protected Request $request;
    private ?Renderer $renderer = null;

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Inject the container-configured Renderer. Called by the Router when it
     * dispatches to a controller so that view() resolves templates against the
     * application's configured views path.
     */
    public function setRenderer(Renderer $renderer): self
    {
        $this->renderer = $renderer;
        return $this;
    }

    protected function view(string $template, array $data = []): Response
    {
        if ($this->renderer === null) {
            throw new \RuntimeException(
                'No Renderer set on ' . static::class . '. Controllers must be dispatched '
                . 'through the Router (which injects the configured Renderer).'
            );
        }

        // Make the CSRF token available to the template unless the caller
        // already supplied one. The token is attached to the request by
        // CsrfMiddleware; absent that middleware it is simply an empty string.
        if (!array_key_exists('_csrf_token', $data) && isset($this->request)) {
            $data['_csrf_token'] = $this->request->getAttribute(CsrfMiddleware::TOKEN_ATTR, '');
        }

        return $this->renderer->render($template, $data);
    }

    protected function json(array $data, int $status = 200): Response
    {
        $response = new Response();
        $response->json($data, $status);
        return $response;
    }

    protected function redirect(string $url, int $status = 302, bool $allowExternal = false): Response
    {
        $response = new Response();
        $response->redirect($url, $status, $allowExternal);
        return $response;
    }

    protected function back(): Response
    {
        $referer = $this->request->getHeader('Referer') ?? '/';
        if (!$this->isSafeRedirect($referer)) {
            $referer = '/';
        }
        return $this->redirect($referer);
    }

    /**
     * Validate that a redirect URL is same-origin (relative or matching the app host).
     * Rejects absolute URLs with a host component to prevent open redirect attacks.
     */
    protected function isSafeRedirect(string $url): bool
    {
        $url = trim($url);

        // Allow relative paths (starting with /)
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Reject protocol-relative URLs (//evil.com)
        if (str_starts_with($url, '//')) {
            return false;
        }

        // Reject empty paths and anything carrying a scheme or host component.
        if ($url === '') {
            return false;
        }

        $parsed = parse_url($url);
        return !isset($parsed['host']) && !isset($parsed['scheme']);
    }
}