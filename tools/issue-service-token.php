<?php

declare(strict_types=1);

/**
 * Issues a NeNe Clear service token (ADR 0009) for local/ops use.
 *
 * Usage:
 *   php tools/issue-service-token.php --org=1 --scopes=read:invoices,write:payments \
 *       [--sub=service:clear] [--ttl=2592000]
 *
 * Prints the bearer token to stdout. Clear stores it as NENE_INVOICE_BEARER_TOKEN.
 * The token is signed with the same HMAC secret as login tokens; treat it as a
 * secret. (Issuance/revocation via an operator UI is a follow-up.)
 */

use Nene2\Auth\TokenIssuerInterface;
use NeneInvoice\Http\RuntimeContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @return array<string, string> */
function parseArgs(): array
{
    $args = [];
    foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) === 1) {
            $args[$m[1]] = $m[2];
        }
    }

    return $args;
}

$args = parseArgs();

$org = isset($args['org']) && ctype_digit($args['org']) ? (int) $args['org'] : null;
if ($org === null) {
    fwrite(STDERR, "Error: --org=<organization_id> is required.\n");
    exit(1);
}

$scopes = array_values(array_filter(
    explode(',', $args['scopes'] ?? 'read:invoices'),
    static fn (string $s): bool => $s !== '',
));
$sub = $args['sub'] ?? 'service:clear';
$ttl = isset($args['ttl']) && ctype_digit($args['ttl']) ? (int) $args['ttl'] : 2592000; // 30 days

$container = (new RuntimeContainerFactory(dirname(__DIR__)))->create();
$issuer = $container->get(TokenIssuerInterface::class);

if (!$issuer instanceof TokenIssuerInterface) {
    fwrite(STDERR, "Error: token issuer service is unavailable.\n");
    exit(1);
}

$now = time();
$token = $issuer->issue([
    'sub' => $sub,
    'org' => $org,
    'scopes' => $scopes,
    'iat' => $now,
    'exp' => $now + $ttl,
]);

echo $token . "\n";
