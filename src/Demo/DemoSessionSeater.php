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
    public function __construct(
        private RefreshTokenIssuer $refreshTokenIssuer,
        private Psr17Factory $responseFactory,
    ) {
    }

    public function seatAndRedirect(ServerRequestInterface $request, ProvisionedDemoOrg $org): ResponseInterface
    {
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
}
