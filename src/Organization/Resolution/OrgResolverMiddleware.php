<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the current organization from the request and stores its id in a
 * shared {@see RequestScopedHolder} that downstream repositories read to scope
 * every query (ADR 0006). This is the single point where tenant context enters
 * the request, making per-query org filtering impossible to forget.
 *
 * Bypass paths (superadmin org management, health, auth, service API, public
 * download) skip resolution and pass through with the holder unset. Repositories
 * on those routes must not call {@see RequestScopedHolder::get()}.
 *
 * Resolution:
 *  1. strategy->resolve() → slug or custom-domain identifier
 *  2. sole-org fallback (single mode only): if unresolved and exactly one org
 *     exists, use it — keeps zero-config single-org installs working
 *  3. findBySlug() ?? findByCustomDomain()
 *  4. 404 if unresolved/not found, 403 if inactive
 */
final readonly class OrgResolverMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const BYPASS_PREFIXES = [
        '/health',
        '/auth/',
        '/api/',
        '/invoices/download/',
        '/admin/organizations',
        // Self-service identity: `/admin/me` returns the caller's own record from
        // token claims (user lookup is by id, not org-scoped), so it needs no org
        // context. Bypassing lets a cross-tenant superadmin (organization_id NULL)
        // bootstrap the admin SPA where there is no org in the URL (#552).
        '/admin/me',
    ];

    /**
     * @param RequestScopedHolder<int> $orgId
     * @param bool $soleOrgFallback enable the single-org convenience fallback (Env/single mode)
     */
    public function __construct(
        private RequestScopedHolder $orgId,
        private OrganizationRepositoryInterface $repository,
        private ProblemDetailsResponseFactory $problemDetails,
        private OrgResolutionStrategyInterface $strategy,
        private bool $soleOrgFallback,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        foreach (self::BYPASS_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $handler->handle($request);
            }
        }

        $identifier = $this->strategy->resolve($request);

        $org = $identifier !== null
            ? ($this->repository->findBySlug($identifier) ?? $this->repository->findByCustomDomain($identifier))
            : ($this->soleOrgFallback ? $this->soleOrganization() : null);

        if ($org === null) {
            return $identifier === null
                ? $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Not Resolved', 404, 'Could not determine the organization for this request. Check the TENANT_RESOLUTION configuration.')
                : $this->problemDetails->create($request, 'organization-not-found', 'Organization Not Found', 404, "No organization found for '{$identifier}'.");
        }

        if (!$org->isActive) {
            return $this->problemDetails->create($request, 'organization-inactive', 'Organization Inactive', 403, 'This organization is currently inactive.');
        }

        $this->orgId->set($org->id ?? 0);

        return $handler->handle(
            $request->withAttribute('nene2.org.id', $org->id)
                ->withAttribute('nene2.org.slug', $org->slug),
        );
    }

    /**
     * Returns the only organization when exactly one exists, else null. Used by
     * the single-org fallback so an install with one tenant needs no ORG_SLUG.
     */
    private function soleOrganization(): ?\NeneInvoice\Organization\Organization
    {
        if ($this->repository->count() !== 1) {
            return null;
        }

        return $this->repository->findAll(1, 0)[0] ?? null;
    }
}
