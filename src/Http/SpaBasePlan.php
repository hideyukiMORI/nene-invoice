<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

/**
 * Decides how the SPA shell is served for a request, merging the two independent
 * URL axes (型B Phase 2):
 *
 *  - the install base path (ADR 0015), detected from `SCRIPT_NAME`; and
 *  - the org slug prefix of path tenancy (`/acme/...`), present only in `path`
 *    resolution mode.
 *
 * The front controller serves the shell *before* the router (so SPA deep-links
 * never hit the org resolver's 404-on-unresolved), which is why this decision
 * lives here rather than in the middleware pipeline. Three outputs:
 *
 *  - {@see $assetBase} — install base only; static assets are one physical copy
 *    under `admin/`, never under a slug.
 *  - {@see $appBase} — install base plus the tenant slug, injected as `app-base`
 *    so the SPA router basename and every API call stay under `/<slug>/`.
 *  - {@see $spaPath} — the slug-stripped, base-relative path used to decide
 *    shell-vs-API ({@see BasePath::isApiPath()}), matching the canonical path the
 *    router will ultimately see after {@see \NeneInvoice\Organization\Resolution\OrgResolverMiddleware}
 *    strips the slug.
 *
 * Outside `path` mode the slug axis is absent: app base equals asset base and the
 * path is unchanged, preserving single/subdomain/custom-domain behaviour exactly.
 */
final readonly class SpaBasePlan
{
    private function __construct(
        public string $assetBase,
        public string $appBase,
        public string $spaPath,
    ) {
    }

    /**
     * @param string                 $installBase  normalized install base (`''` or `/invoice`)
     * @param string                 $strippedPath request path with the install base already removed
     * @param string                 $mode         TENANT_RESOLUTION value (`single` / `path` / `subdomain` / `custom_domain`)
     * @param callable(string): bool $slugExists   true when the segment names a real organization (called only in path mode)
     */
    public static function resolve(string $installBase, string $strippedPath, string $mode, callable $slugExists): self
    {
        if ($mode !== 'path') {
            return new self($installBase, $installBase, $strippedPath);
        }

        $parts     = explode('/', ltrim($strippedPath, '/'), 2);
        $candidate = $parts[0];

        // No first segment (install root), or the segment is not a tenant slug
        // (e.g. the org-less superadmin route `/organizations`): serve at the
        // install base with no slug prefix.
        if ($candidate === '' || !$slugExists($candidate)) {
            return new self($installBase, $installBase, $strippedPath);
        }

        return new self(
            assetBase: $installBase,
            appBase: $installBase . '/' . $candidate,
            spaPath: '/' . ($parts[1] ?? ''),
        );
    }
}
