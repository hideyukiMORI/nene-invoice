<?php

declare(strict_types=1);

namespace NeneInvoice\Organization\Resolution;

/**
 * A resolution strategy that carries the org identifier in the request *path*
 * (as opposed to the host or an env var), and therefore must remove that segment
 * before the router matches routes registered without the tenant prefix.
 *
 * Only {@see PathPrefixResolutionStrategy} needs this: `/org1/admin/...` must
 * become `/admin/...` so the existing `/admin/...` routes match (型B path
 * tenancy). Host- and env-based strategies leave the path untouched and do not
 * implement this interface.
 *
 * The strip runs inside {@see OrgResolverMiddleware}, which is the first entry in
 * the pipeline — so every downstream middleware (bearer-token / capability, which
 * gate on `/admin` · `/api` prefixes) and the router see the canonical,
 * tenant-stripped path. Stripping later would let a `/org1/admin/...` request
 * slip past the prefix-based auth guards.
 */
interface PathScopedResolutionStrategyInterface extends OrgResolutionStrategyInterface
{
    /**
     * Removes the leading org path segment this strategy consumed, returning the
     * router-facing path (always leading-slash; `/` when nothing remains). Only
     * called after {@see OrgResolutionStrategyInterface::resolve()} returned a
     * non-null identifier for this request.
     */
    public function stripPrefix(string $path): string;
}
