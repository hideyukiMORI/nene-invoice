<?php

declare(strict_types=1);

namespace NeneInvoice\Mcp;

/**
 * Selects which catalog tools the local MCP server exposes (ADR 0021).
 *
 * Read-only by default: only `safety: read` tools are served. `admin`-safety
 * tools (cross-tenant / oversight reads) are exposed only when the operator opts
 * in with `NENE2_LOCAL_MCP_INCLUDE_ADMIN=1`. This narrows *exposure* only — the
 * real authorization gate stays at the API's OrgGuard + capability boundary, so a
 * token whose role does not satisfy an endpoint is still rejected there.
 *
 * Pure (array in, array out) so it is unit testable against the committed
 * `docs/mcp/tools.json`. `LocalMcpToolCatalog` reads a file path and offers no
 * filter hook, so the runner writes the filtered result to a temporary catalog.
 */
final class LocalMcpCatalogFilter
{
    /**
     * @param list<array<string, mixed>> $tools
     *
     * @return list<array<string, mixed>>
     */
    public static function apply(array $tools, bool $includeAdmin): array
    {
        if ($includeAdmin) {
            return $tools;
        }

        return array_values(array_filter(
            $tools,
            static fn (array $tool): bool => ($tool['safety'] ?? null) === 'read',
        ));
    }
}
