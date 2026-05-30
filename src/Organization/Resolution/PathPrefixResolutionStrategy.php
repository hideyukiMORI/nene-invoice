<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the org slug from the first URL path segment: /org1/admin/... → "org1".
 *
 * Best for shared-host deployments where wildcard subdomains are not available.
 * Bypass paths (health, auth, service API, public download, superadmin org
 * management) return null so the middleware passes them through unresolved.
 */
final readonly class PathPrefixResolutionStrategy implements OrgResolutionStrategyInterface
{
    /** @var list<string> */
    private const BYPASS_PREFIXES = [
        '/health',
        '/auth/',
        '/api/',
        '/invoices/download/',
        '/admin/organizations',
    ];

    public function resolve(ServerRequestInterface $request): ?string
    {
        $path = $request->getUri()->getPath();

        foreach (self::BYPASS_PREFIXES as $bypass) {
            if (str_starts_with($path, $bypass)) {
                return null;
            }
        }

        $parts     = explode('/', ltrim($path, '/'), 2);
        $candidate = $parts[0];

        return $candidate !== '' ? $candidate : null;
    }
}
