<?php

declare(strict_types=1);

/**
 * Local, dev-only MCP server for NeNe Invoice (ADR 0021).
 *
 * Consumes NENE2's Nene2\Mcp\LocalMcpServer: reads the committed catalog
 * (docs/mcp/tools.json), proxies each tool call to the local HTTP API, and
 * speaks newline-delimited JSON-RPC 2.0 (initialize / tools/list / tools/call)
 * over stdio. It binds NO network port; the only reachable target is the local
 * API base URL. Do NOT ship this in a release artifact and do NOT auto-start it —
 * it is a development convenience, not a production backdoor.
 *
 * Usage (host-run, app on :8510):
 *   php tools/local-mcp-server.php
 *   NENE2_LOCAL_API_BASE_URL=http://app php tools/local-mcp-server.php   # inside Compose
 *
 * Authentication (ADR 0021 option C — fail-closed):
 *   1. NENE2_LOCAL_MCP_BEARER set                        → used verbatim.
 *   2. else NENE2_ALLOW_DEV_SECRET=1 + NENE2_LOCAL_JWT_SECRET set
 *                                                        → mint {sub, role, org}.
 *   3. else no token → only getHealth works; /admin/* return 401.
 *
 * Exposure: read-only by default; set NENE2_LOCAL_MCP_INCLUDE_ADMIN=1 to also
 * expose the cross-tenant / oversight admin reads (still gated by the API).
 *
 * See docs/development/local-mcp-server.md and .env.example for the full env list.
 */

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Mcp\LocalMcpException;
use Nene2\Mcp\LocalMcpServer;
use Nene2\Mcp\LocalMcpToolCatalog;
use Nene2\Mcp\NativeLocalMcpHttpClient;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Mcp\LocalMcpAuthPlan;
use NeneInvoice\Mcp\LocalMcpCatalogFilter;
use NeneInvoice\Mcp\LocalMcpConfigException;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

$apiBaseUrl = getenv('NENE2_LOCAL_API_BASE_URL');
if (!is_string($apiBaseUrl) || $apiBaseUrl === '') {
    $apiBaseUrl = 'http://localhost:8510';
}

// STDERR carries diagnostics + the state-changing-call audit trail. STDOUT is
// reserved for the JSON-RPC protocol and must never be polluted.
$auditHandler = new StreamHandler('php://stderr', Level::Info);
$auditHandler->setFormatter(new JsonFormatter());
$logger = new Logger('invoice-local-mcp', [$auditHandler]);

$includeAdmin = getenv('NENE2_LOCAL_MCP_INCLUDE_ADMIN') === '1';

try {
    $plan = LocalMcpAuthPlan::fromEnv(getenv());
} catch (LocalMcpConfigException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

$bearerToken = $plan->preIssuedToken;

if ($bearerToken === null && $plan->mintClaims !== null) {
    // Mint through the app's own issuer so the token is signed with the exact
    // secret the running API verifies with (GuardedJwtSecretResolver), not a
    // hand-rolled verifier that could drift from it.
    $container = (new RuntimeContainerFactory($root))->create();
    $issuer = $container->get(TokenIssuerInterface::class);

    if (!$issuer instanceof TokenIssuerInterface) {
        fwrite(STDERR, "Token issuer is unavailable; cannot mint a dev MCP token.\n");
        exit(1);
    }

    $now = time();
    $bearerToken = $issuer->issue($plan->mintClaims + ['iat' => $now, 'exp' => $now + 86400]);
}

// Read-only by default: write the filtered catalog to a temp file, since
// LocalMcpToolCatalog reads a path and offers no filter hook.
$catalog = json_decode((string) file_get_contents($root . '/docs/mcp/tools.json'), true, 512, JSON_THROW_ON_ERROR);
$allTools = is_array($catalog) && is_array($catalog['tools'] ?? null) ? $catalog['tools'] : [];
$exposedTools = LocalMcpCatalogFilter::apply(array_values(array_filter($allTools, 'is_array')), $includeAdmin);

$catalogPath = tempnam(sys_get_temp_dir(), 'invoice-mcp-catalog-');
file_put_contents(
    $catalogPath,
    json_encode(
        ['version' => 1, 'source' => 'docs/openapi/openapi.yaml', 'tools' => $exposedTools],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) . "\n",
);
register_shutdown_function(static fn () => @unlink($catalogPath));

$logger->info('Local MCP server starting.', [
    'api_base_url' => $apiBaseUrl,
    'exposure' => $includeAdmin ? 'read+admin' : 'read-only',
    'tool_count' => count($exposedTools),
    'auth' => $plan->preIssuedToken !== null ? 'pre-issued' : ($plan->mintClaims !== null ? 'minted' : 'none'),
]);

$server = new LocalMcpServer(
    new LocalMcpToolCatalog($catalogPath),
    new NativeLocalMcpHttpClient($bearerToken),
    $apiBaseUrl,
    $logger,
);

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    try {
        $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($message)) {
            throw new LocalMcpException('JSON-RPC message must be an object.');
        }

        $response = $server->handle($message);

        if ($response === null) {
            continue;
        }
    } catch (Throwable $exception) {
        $response = [
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32700,
                'message' => $exception->getMessage(),
            ],
        ];
    }

    fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}
