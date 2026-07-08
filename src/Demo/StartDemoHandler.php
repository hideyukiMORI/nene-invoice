<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Auth\SessionCookies;
use NeneInvoice\Http\BasePath;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /demo/{template}` — public, gated by `DEMO_MODE=1`. Provisions a brand-new
 * **disposable** organization, seeds it with industry data, seats a session, and
 * redirects into the freshly minted tenant's SPA (2026-07-07 「使い捨て org」デモ).
 *
 * Auth handoff (方式A, path-tenancy): this issues the same `ni_refresh` + `ni_csrf`
 * cookies a login would, but **scoped to `/{slug}/auth`** so the browser sends
 * them to the SPA's slug-prefixed `POST /{slug}/auth/refresh` on first load. The
 * SPA obtains its in-memory access token from that one silent refresh (auth-gate)
 * — no access token is handed over here, and the authentication core (rotation /
 * SessionCookies body) is untouched. Reloading the tenant SPA drops to the login
 * screen (one-shot); the intended path is to re-hit `/demo/{template}` for a
 * fresh org, which is the "reset to initial state" affordance.
 */
final readonly class StartDemoHandler implements RequestHandlerInterface
{
    /** How many random slugs to try before giving up on a collision. */
    private const SLUG_ATTEMPTS = 5;

    public function __construct(
        private CreateOrganizationUseCaseInterface $createOrganization,
        private DatabaseQueryExecutorInterface $query,
        private DemoDataSeeder $seeder,
        private RefreshTokenIssuer $refreshTokenIssuer,
        private ProblemDetailsResponseFactory $problemDetails,
        private Psr17Factory $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->env('DEMO_MODE', '0') !== '1') {
            return $this->problemDetails->create($request, 'not-found', 'Not Found', 404, 'Demo mode is not enabled on this instance.');
        }

        $params = (array) $request->getAttribute(\Nene2\Routing\Router::PARAMETERS_ATTRIBUTE, []);
        $templateKey = isset($params['template']) && is_string($params['template']) ? $params['template'] : '';
        $template = DemoTemplate::tryFrom($templateKey);

        if ($template === null) {
            return $this->problemDetails->create($request, 'not-found', 'Not Found', 404, "Unknown demo template '{$templateKey}'.");
        }

        $organization = $this->createDisposableOrg($template);
        $slug = $organization->slug;
        $orgId = $organization->id ?? 0;

        $this->seeder->seed($orgId, $template);

        // The demo admin was provisioned in the same transaction as the org; look
        // up its id to seat a refresh-token family for it (cross-tenant read via
        // the shared executor — no second connection).
        $adminRow = $this->query->fetchOne(
            'SELECT id FROM users WHERE organization_id = ? AND role = ? ORDER BY id ASC LIMIT 1',
            [$orgId, 'admin'],
        );
        $adminId = (int) ($adminRow['id'] ?? 0);

        $refreshToken = $this->refreshTokenIssuer->issue($adminId, $orgId);

        // Scope the session cookies to the tenant slug so the browser sends them
        // to the SPA's slug-prefixed /{slug}/auth/refresh (方式A). The install base
        // (ADR 0015) is '' at the document root, '/invoice' under a subdirectory.
        $installBase = BasePath::fromRequest($request);
        $slugBase = $installBase . '/' . $slug;
        $csrfToken = RefreshTokenSecret::generateCsrfToken();

        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $slugBase . '/dashboard')
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', SessionCookies::setRefresh($refreshToken->rawToken, $refreshToken->expiresAtTimestamp, $slugBase))
            ->withAddedHeader('Set-Cookie', SessionCookies::setCsrf($csrfToken, $refreshToken->expiresAtTimestamp, $slugBase));
    }

    private function createDisposableOrg(DemoTemplate $template): \NeneInvoice\Organization\Organization
    {
        $lastError = null;
        for ($attempt = 0; $attempt < self::SLUG_ATTEMPTS; $attempt++) {
            $slug = 'demo-' . bin2hex(random_bytes(4));
            $input = new CreateOrganizationInput(
                name: $this->companyName($template),
                slug: $slug,
                plan: 'free',
                adminEmail: 'admin@' . $slug . '.demo.local',
                adminPassword: bin2hex(random_bytes(16)),
            );

            try {
                return $this->createOrganization->execute(null, $input);
            } catch (OrganizationSlugConflictException $e) {
                $lastError = $e;
                continue;
            }
        }

        throw $lastError ?? new OrganizationSlugConflictException('demo');
    }

    private function companyName(DemoTemplate $template): string
    {
        return match ($template) {
            DemoTemplate::Kensetsu => '株式会社山手建設',
            DemoTemplate::Bldmainte => '株式会社クリーンサポート東京',
            DemoTemplate::Seisaku => '株式会社アトリエノート',
        };
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
