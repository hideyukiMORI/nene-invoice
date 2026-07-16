<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Mcp;

use PHPUnit\Framework\TestCase;

/**
 * Enforces the ADR 0021 invariant that the dev-only local MCP server is **never
 * shipped in a release artifact**. `tools/build-release.sh` copies `tools/` via
 * an explicit allow-list (only the scripts an operator runs in production), so
 * the runner is excluded today — but "excluded because nobody added it" is an
 * implicit truth. This test makes it a declared, gated invariant: if someone
 * switches to a wholesale `cp -r tools` or adds the runner to the allow-list, CI
 * turns red instead of silently shipping a dev backdoor into the release ZIP.
 */
final class LocalMcpServerReleaseExclusionTest extends TestCase
{
    private function buildScript(): string
    {
        $path = dirname(__DIR__, 2) . '/tools/build-release.sh';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_release_build_does_not_ship_the_local_mcp_runner(): void
    {
        self::assertStringNotContainsString(
            'local-mcp-server.php',
            $this->buildScript(),
            'The dev-only MCP runner must not be copied into the release artifact (ADR 0021).',
        );
    }

    public function test_release_build_uses_a_tools_allow_list_not_a_wholesale_copy(): void
    {
        $script = $this->buildScript();

        // A recursive copy of tools/ would sweep the dev-only runner into the ZIP.
        self::assertDoesNotMatchRegularExpression(
            '/cp\s+-r\s+"?\$\{?ROOT\}?\/tools/',
            $script,
            'tools/ must be shipped via an explicit allow-list, never `cp -r tools` (ADR 0021).',
        );
    }
}
