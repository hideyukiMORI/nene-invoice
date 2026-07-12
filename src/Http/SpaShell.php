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
 *
 * ## Optional demo analytics (env-gated, OSS-clean)
 *
 * When — and only when — `$analyticsEndpoint` is a valid origin (wired from the
 * `DEMO_ANALYTICS_ENDPOINT` env, set on the disposable-demo host alone), a third
 * `<head>` tag is added: the cookieless GoatCounter beacon
 * (`<script data-goatcounter="…/count" async src="…/count.js">`). Because that
 * beacon loads a cross-origin script and posts to a cross-origin collector, this
 * response also carries its own `Content-Security-Policy` that widens
 * `script-src` / `connect-src` / `img-src` to the analytics origin.
 *
 * The OSS release ships **no** analytics origin anywhere: the endpoint literal
 * lives only in the demo host's `.env`, never in `.env.example`, the committed
 * React build, or `.htaccess`. With the env unset the shell is byte-for-byte the
 * pre-analytics shell and sets no CSP header (Apache's default `.htaccess` CSP
 * applies, unchanged). See `public_html/.htaccess` for the Apache side of the
 * hand-off (its default CSP yields to the one this class sets on the shell).
 */
final readonly class SpaShell
{
    /**
     * Mirror of the default `Content-Security-Policy` in `public_html/.htaccess`.
     * Only consulted when demo analytics is enabled: this class then emits the
     * CSP itself (widened for the analytics origin) and `.htaccess` steps aside.
     * Keep this string in lockstep with the `.htaccess` `Header always set
     * Content-Security-Policy` value — a drift here only affects the demo shell.
     */
    private const string BASE_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /** Normalised analytics origin (e.g. `https://stats.example.test`), or null when disabled. */
    private ?string $analyticsEndpoint;

    public function __construct(
        private string $shellPath,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        ?string $analyticsEndpoint = null,
    ) {
        $this->analyticsEndpoint = self::normaliseEndpoint($analyticsEndpoint);
    }

    /**
     * Accepts an analytics endpoint only when it is a bare `http(s)://host[:port]`
     * origin — no path, query, fragment, whitespace or control characters. Any
     * trailing slash is trimmed. Anything else (including empty/unset) disables
     * analytics (fail-safe), so a fat-fingered env can never inject markup or a
     * malformed CSP / header. The value is operator-controlled (server `.env`),
     * but validating it keeps header/attribute construction provably safe.
     */
    private static function normaliseEndpoint(?string $endpoint): ?string
    {
        if ($endpoint === null) {
            return null;
        }

        $endpoint = rtrim(trim($endpoint), '/');

        if ($endpoint === '' || preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?$#', $endpoint) !== 1) {
            return null;
        }

        return $endpoint;
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

        // Only when demo analytics is on does this class own the shell's CSP
        // (widened for the analytics origin); `.htaccess` yields to it. With the
        // env unset no CSP header is set here and the Apache default applies.
        if ($this->analyticsEndpoint !== null) {
            $response = $response->withHeader('Content-Security-Policy', $this->analyticsCsp());
        }

        return $response;
    }

    private function inject(string $html, string $assetBase, string $appBase): string
    {
        $assetHref = htmlspecialchars($assetBase . '/admin/', ENT_QUOTES);
        $appHref = htmlspecialchars($appBase . '/', ENT_QUOTES);

        $tags = "<head>\n    <base href=\"{$assetHref}\" />\n    <meta name=\"app-base\" content=\"{$appHref}\" />" . $this->analyticsTag();

        // Inject right after the opening <head> so <base> precedes any relative
        // URL. If the marker is absent (unexpected), serve the shell unchanged.
        return str_replace('<head>', $tags, $html);
    }

    /**
     * The cookieless GoatCounter beacon, or an empty string when analytics is
     * disabled. Both URLs derive from the single validated origin; the origin is
     * escaped for the HTML attribute context (belt-and-suspenders — the endpoint
     * is already validated to a bare origin).
     */
    private function analyticsTag(): string
    {
        if ($this->analyticsEndpoint === null) {
            return '';
        }

        $dataAttr = htmlspecialchars($this->analyticsEndpoint . '/count', ENT_QUOTES);
        $src = htmlspecialchars($this->analyticsEndpoint . '/count.js', ENT_QUOTES);

        return "\n    <script data-goatcounter=\"{$dataAttr}\" async src=\"{$src}\"></script>";
    }

    /**
     * The default CSP widened so the beacon may load its cross-origin script and
     * report to its cross-origin collector. GoatCounter's `count.js` posts via
     * `navigator.sendBeacon` (connect-src) with an `Image()` fallback (img-src),
     * so the origin is added to `script-src`, `connect-src` and `img-src`.
     */
    private function analyticsCsp(): string
    {
        $origin = $this->analyticsEndpoint;

        return str_replace(
            ["script-src 'self'", "connect-src 'self'", "img-src 'self' data:"],
            ["script-src 'self' {$origin}", "connect-src 'self' {$origin}", "img-src 'self' data: {$origin}"],
            self::BASE_CSP,
        );
    }
}
