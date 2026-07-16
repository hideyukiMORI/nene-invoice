<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Mcp;

use NeneInvoice\Mcp\LocalMcpCatalogFilter;
use PHPUnit\Framework\TestCase;

/**
 * Proves the read-only-by-default exposure of ADR 0021, asserted against the
 * committed docs/mcp/tools.json so the guarantee tracks the real catalog: with
 * the admin opt-in off, only `safety: read` tools are served.
 */
final class LocalMcpCatalogFilterTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function committedTools(): array
    {
        $catalog = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/docs/mcp/tools.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($catalog);
        self::assertIsArray($catalog['tools'] ?? null);

        return array_values(array_filter($catalog['tools'], 'is_array'));
    }

    public function test_default_exposes_only_read_tools(): void
    {
        $filtered = LocalMcpCatalogFilter::apply($this->committedTools(), includeAdmin: false);

        self::assertNotEmpty($filtered);

        foreach ($filtered as $tool) {
            self::assertSame('read', $tool['safety'], sprintf('tool "%s" leaked into the read-only set', $tool['name']));
        }
    }

    public function test_default_drops_the_admin_tools_present_in_the_catalog(): void
    {
        $all = $this->committedTools();
        $filtered = LocalMcpCatalogFilter::apply($all, includeAdmin: false);

        $adminCount = count(array_filter($all, static fn (array $t): bool => ($t['safety'] ?? null) === 'admin'));

        // The catalog genuinely contains admin tools (otherwise this test proves nothing).
        self::assertGreaterThan(0, $adminCount);
        self::assertCount(count($all) - $adminCount, $filtered);
    }

    public function test_optin_exposes_every_catalog_tool(): void
    {
        $all = $this->committedTools();
        $filtered = LocalMcpCatalogFilter::apply($all, includeAdmin: true);

        self::assertCount(count($all), $filtered);
    }
}
