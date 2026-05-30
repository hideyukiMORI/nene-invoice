<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the org by its full custom domain: org1.com → looked up against
 * organizations.custom_domain. Use when tenants bring their own domain
 * (CNAME → this server). Returns the raw Host header; the middleware resolves
 * it via {@see \NeneInvoice\Organization\OrganizationRepositoryInterface::findByCustomDomain()}.
 */
final readonly class CustomDomainResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = $request->getUri()->getHost();

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return $host !== '' ? $host : null;
    }
}
