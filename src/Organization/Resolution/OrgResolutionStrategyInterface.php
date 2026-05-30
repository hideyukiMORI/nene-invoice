<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the organization slug (or custom-domain identifier) from an incoming
 * HTTP request. Implementations cover the resolution modes of ADR 0006:
 *
 *  - {@see EnvResolutionStrategy}          — ORG_SLUG env var (single / dev)
 *  - {@see PathPrefixResolutionStrategy}   — /org1/admin/... → "org1"
 *  - {@see SubdomainResolutionStrategy}    — org1.example.com → "org1"
 *  - {@see CustomDomainResolutionStrategy} — org1.com → looked up by custom_domain
 *
 * Returns null when this strategy cannot determine an org from the request.
 */
interface OrgResolutionStrategyInterface
{
    public function resolve(ServerRequestInterface $request): ?string;
}
