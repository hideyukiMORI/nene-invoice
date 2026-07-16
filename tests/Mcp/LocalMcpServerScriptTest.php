<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Mcp;

use PHPUnit\Framework\TestCase;

/**
 * Runs the real `tools/local-mcp-server.php` as a subprocess and pins its
 * behaviour end-to-end over stdio JSON-RPC (ADR 0021) — the "does it actually
 * run" gate, not just the pure units.
 *
 * `initialize` and `tools/list` never touch the HTTP API, so these assertions
 * need no running app and are deterministic in CI: the read-only default exposes
 * exactly the `safety: read` tools, the admin opt-in widens the set, and a
 * superadmin mint without the admin opt-in fails closed at startup.
 * `tools/call` (which proxies to the API) is exercised by tools/mcp-smoke.sh
 * against a running app, not here.
 */
final class LocalMcpServerScriptTest extends TestCase
{
    /**
     * @param array<string, string> $extraEnv
     * @param list<array<string, mixed>> $messages
     *
     * @return array{int, string, string}
     */
    private function runServer(array $messages, array $extraEnv = []): array
    {
        $env = ['PATH' => (string) getenv('PATH')] + $extraEnv;

        $process = proc_open(
            ['php', self::root() . '/tools/local-mcp-server.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            self::root(),
            $env,
        );

        self::assertIsResource($process);

        foreach ($messages as $message) {
            fwrite($pipes[0], json_encode($message, JSON_THROW_ON_ERROR) . "\n");
        }
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /**
     * @param string $stdout newline-delimited JSON-RPC responses
     *
     * @return array<int, array<string, mixed>> responses keyed by their id
     */
    private function responsesById(string $stdout): array
    {
        $byId = [];

        foreach (explode("\n", trim($stdout)) as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $byId[$decoded['id']] = $decoded;
        }

        return $byId;
    }

    public function test_read_only_default_exposes_only_read_tools(): void
    {
        [$exit, $stdout] = $this->runServer([
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
        ]);

        self::assertSame(0, $exit);
        $tools = $this->responsesById($stdout)[2]['result']['tools'];

        self::assertNotEmpty($tools);
        foreach ($tools as $tool) {
            self::assertTrue(
                $tool['annotations']['readOnlyHint'],
                sprintf('tool "%s" leaked into the read-only default', $tool['name']),
            );
        }

        $names = array_column($tools, 'name');
        self::assertNotContains('listOrganizations', $names);
        self::assertNotContains('listUsers', $names);
        self::assertNotContains('listAuditLogs', $names);
    }

    public function test_admin_optin_exposes_more_tools_than_the_default(): void
    {
        [, $defaultOut] = $this->runServer([
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
        ]);
        [$exit, $adminOut] = $this->runServer(
            [['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []]],
            ['NENE2_LOCAL_MCP_INCLUDE_ADMIN' => '1'],
        );

        self::assertSame(0, $exit);

        $defaultCount = count($this->responsesById($defaultOut)[2]['result']['tools']);
        $adminNames = array_column($this->responsesById($adminOut)[2]['result']['tools'], 'name');

        self::assertGreaterThan($defaultCount, count($adminNames));
        self::assertContains('listOrganizations', $adminNames);
    }

    public function test_superadmin_mint_without_admin_optin_fails_closed_at_startup(): void
    {
        [$exit, $stdout, $stderr] = $this->runServer(
            [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]],
            ['NENE2_ALLOW_DEV_SECRET' => '1', 'NENE2_LOCAL_JWT_SECRET' => 'dev', 'NENE2_LOCAL_MCP_ROLE' => 'superadmin'],
        );

        self::assertSame(1, $exit, 'server must refuse a superadmin mint without the admin opt-in');
        self::assertStringContainsString('superadmin', $stderr);
        self::assertSame('', trim($stdout), 'no JSON-RPC response should be emitted on a fail-closed startup');
    }

    public function test_unknown_method_returns_json_rpc_method_not_found(): void
    {
        [$exit, $stdout] = $this->runServer([
            ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'bogus', 'params' => []],
        ]);

        self::assertSame(0, $exit);
        self::assertSame(-32601, $this->responsesById($stdout)[9]['error']['code']);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
