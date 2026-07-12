<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Demo\DemoSessionSeaterInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Auth\SessionCookies;
use NeneInvoice\Http\BasePath;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Seats the demo admin's session and 302-redirects into the fresh tenant's SPA.
 *
 * Auth handoff (方式A, path-tenancy): this issues the same `ni_refresh` +
 * `ni_csrf` cookies a login would, but **scoped to `/{slug}`** so the browser
 * sends them to the SPA's slug-prefixed `POST /{slug}/auth/refresh` on first
 * load. The SPA obtains its in-memory access token from that one silent refresh
 * (auth-gate) — no access token is handed over here, and the authentication core
 * (rotation / SessionCookies body) is untouched. Reloading the tenant SPA drops
 * to the login screen (one-shot); the intended path is to re-hit
 * `/demo/{template}` for a fresh org, which is the "reset to initial state"
 * affordance. This one-shot semantic is an invoice product decision and stays
 * inside this class (see {@see DemoSessionSeaterInterface}).
 */
final readonly class DemoSessionSeater implements DemoSessionSeaterInterface
{
    /** @var \Closure(string): void Where entry log lines go; defaults to `error_log`. */
    private \Closure $logSink;

    /**
     * @param (\Closure(string): void)|null $logSink Sink for the demo-entry
     *        attribution line (#658). Defaults to PHP's `error_log`, matching the
     *        product's existing convention; overridable in tests so the recorded
     *        line can be asserted without depending on the global `error_log` ini.
     */
    public function __construct(
        private RefreshTokenIssuer $refreshTokenIssuer,
        private Psr17Factory $responseFactory,
        ?\Closure $logSink = null,
    ) {
        $this->logSink = $logSink ?? static function (string $line): void {
            error_log($line);
        };
    }

    public function seatAndRedirect(ServerRequestInterface $request, ProvisionedDemoOrg $org): ResponseInterface
    {
        // Attribution layer 1 (#658): record channel/campaign here — the last
        // moment they exist — because the 302 below drops the query and the
        // browser's next request to /{slug}/dashboard is same-origin (no UTM, a
        // self Referer). No PII: only Referer + utm_* + the disposable slug are
        // logged; never the client IP or any personal field.
        $this->logDemoEntry($request, $org);

        $refreshToken = $this->refreshTokenIssuer->issue($org->adminUserId, $org->orgId);

        // Scope the session cookies to the tenant slug so the browser sends them
        // to the SPA's slug-prefixed /{slug}/auth/refresh (方式A). The install base
        // (ADR 0015) is '' at the document root, '/invoice' under a subdirectory.
        $installBase = BasePath::fromRequest($request);
        $slugBase = $installBase . '/' . $org->slug;
        $csrfToken = RefreshTokenSecret::generateCsrfToken();

        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $slugBase . '/dashboard')
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', SessionCookies::setRefresh($refreshToken->rawToken, $refreshToken->expiresAtTimestamp, $slugBase))
            ->withAddedHeader('Set-Cookie', SessionCookies::setCsrf($csrfToken, $refreshToken->expiresAtTimestamp, $slugBase));
    }

    /**
     * Emits one `error_log` line per demo entry with the referer and UTM tags,
     * matching the product's existing `error_log('NeNe Invoice: …')` convention.
     * Values are sanitised (control chars stripped, length-capped) so a crafted
     * Referer / query cannot forge log lines, and missing tags render as `-` so
     * a UTM-less entry still logs cleanly instead of breaking the redirect.
     */
    private function logDemoEntry(ServerRequestInterface $request, ProvisionedDemoOrg $org): void
    {
        $query = $request->getQueryParams();

        $fields = [
            'slug' => $org->slug,
            'utm_source' => $query['utm_source'] ?? null,
            'utm_medium' => $query['utm_medium'] ?? null,
            'utm_campaign' => $query['utm_campaign'] ?? null,
            'referer' => $request->getHeaderLine('Referer'),
        ];

        $parts = [];

        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . self::sanitiseLogValue(is_string($value) ? $value : null);
        }

        ($this->logSink)('NeNe Invoice: demo-entry ' . implode(' ', $parts));
    }

    /**
     * Renders a log field value: `-` when absent/empty, otherwise the value with
     * CR/LF and other control characters removed (log-injection defence) and
     * capped at 256 chars so a long crafted URL can't bloat the log.
     */
    private static function sanitiseLogValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        $clean = trim($clean);

        if ($clean === '') {
            return '-';
        }

        return mb_substr($clean, 0, 256);
    }
}
