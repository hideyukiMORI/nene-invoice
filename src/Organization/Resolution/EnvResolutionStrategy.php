<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the org slug from the ORG_SLUG environment variable.
 *
 * Intended for single-organization installs (the Tier A default) and local
 * development. Returns null when ORG_SLUG is not set — the middleware then
 * applies its sole-organization fallback (see {@see OrgResolverMiddleware}).
 */
final readonly class EnvResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function __construct(private string $orgSlug)
    {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        return $this->orgSlug !== '' ? $this->orgSlug : null;
    }
}
