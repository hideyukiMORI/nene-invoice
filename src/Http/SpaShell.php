<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves the built admin SPA shell for non-API routes, injecting the detected
 * install base so one artifact works at any path (ADR 0015) — and, under path
 * tenancy, the org prefix so the tenant SPA runs under `/<slug>/` (型B Phase 2).
 *
 * Two tags are injected into `<head>` — both CSP-safe (no inline script, which
 * `script-src 'self'` would block). They intentionally use *different* bases:
 *
 * - `<base href="<assetBase>/admin/">` so the shell's relative asset references
 *   (`./assets/…`, built by Vite with `base: './'`) resolve to the real files
 *   under `admin/`. Assets are a single physical copy under the install root, so
 *   this is the install base only — never the org slug.
 * - `<meta name="app-base" content="<appBase>/">` which the frontend reads to set
 *   the router basename and prefix every API call. This is the install base plus
 *   the tenant slug (`/invoice/acme`) in path mode, so the tenant SPA routes and
 *   calls stay under `/<slug>/`; it equals the install base when there is no slug
 *   (single/subdomain installs, or the org-less superadmin shell).
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
     *
     * @param string $assetBase install base for static assets (`''` at root, `/invoice`)
     * @param string $appBase   install base plus tenant slug for router/API (`''`, `/invoice`, `/invoice/acme`)
     */
    public function serve(string $assetBase, string $appBase): ?ResponseInterface
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
            ->withBody($this->streamFactory->createStream($this->inject($html, $assetBase, $appBase)));

        return $response;
    }

    private function inject(string $html, string $assetBase, string $appBase): string
    {
        $assetHref = htmlspecialchars($assetBase . '/admin/', ENT_QUOTES);
        $appHref = htmlspecialchars($appBase . '/', ENT_QUOTES);

        $tags = "<head>\n    <base href=\"{$assetHref}\" />\n    <meta name=\"app-base\" content=\"{$appHref}\" />";

        // Inject right after the opening <head> so <base> precedes any relative
        // URL. If the marker is absent (unexpected), serve the shell unchanged.
        return str_replace('<head>', $tags, $html);
    }
}
