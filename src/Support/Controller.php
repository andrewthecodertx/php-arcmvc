<?php

declare(strict_types=1);

namespace Arc\Support;

use Arc\Application;
use Arc\Http\Request;
use Arc\Http\Response;

abstract class Controller
{
    protected Request $request;
    private ?Application $app = null;

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function setApp(Application $app): self
    {
        $this->app = $app;
        return $this;
    }

    protected function view(string $template, array $data = []): Response
    {
        $renderer = $this->app
            ? $this->app->make(\Arc\View\Renderer::class)
            : new \Arc\View\Renderer();

        return $renderer->render($template, $data);
    }

    protected function json(array $data, int $status = 200): Response
    {
        $response = new Response();
        $response->json($data, $status);
        return $response;
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        $response = new Response();
        $response->redirect($url, $status);
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

        // Parse absolute URLs and reject anything with a host
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            return false;
        }

        return false;
    }
}