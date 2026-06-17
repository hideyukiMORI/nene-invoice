<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves the built admin SPA shell for non-API routes, injecting the detected
 * install base so one artifact works at any path (ADR 0015).
 *
 * Two tags are injected into `<head>` — both CSP-safe (no inline script, which
 * `script-src 'self'` would block):
 *
 * - `<base href="<base>/admin/">` so the shell's relative asset references
 *   (`./assets/…`, built by Vite with `base: './'`) resolve to the real files
 *   under `admin/`, even though the shell itself is served from the install root.
 * - `<meta name="app-base" content="<base>/">` which the frontend reads to set
 *   the router basename and prefix every API call.
 *
 * Serving the shell through the front controller (rather than a static file)
 * also fixes SPA deep-links / F5: any non-API GET returns the shell.
 */
final readonly class SpaShell
{
    public function __construct(
        private string $shellPath,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Returns the base-injected shell, or null when the built shell is absent
     * (e.g. a backend-only checkout) so the caller can fall back to the API.
     */
    public function serve(string $base): ?ResponseInterface
    {
        if (!is_file($this->shellPath)) {
            return null;
        }

        $html = file_get_contents($this->shellPath);

        if ($html === false) {
            return null;
        }

        $response = $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($this->inject($html, $base)));

        return $response;
    }

    private function inject(string $html, string $base): string
    {
        $assetBase = htmlspecialchars($base . '/admin/', ENT_QUOTES);
        $appBase = htmlspecialchars($base . '/', ENT_QUOTES);

        $tags = "<head>\n    <base href=\"{$assetBase}\" />\n    <meta name=\"app-base\" content=\"{$appBase}\" />";

        // Inject right after the opening <head> so <base> precedes any relative
        // URL. If the marker is absent (unexpected), serve the shell unchanged.
        return str_replace('<head>', $tags, $html);
    }
}
