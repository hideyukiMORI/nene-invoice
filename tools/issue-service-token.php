<?php

declare(strict_types=1);

/**
 * Issues a NeNe Clear service token (ADR 0009) for local/ops use.
 *
 * Usage:
 *   php tools/issue-service-token.php --org=1 --scopes=read:invoices,write:payments \
 *       [--label="NeNe Clear"] [--sub=service:clear] [--ttl=2592000]
 *
 * Prints the bearer token to stdout. Clear stores it as NENE_INVOICE_BEARER_TOKEN.
 * The token is signed with the same HMAC secret as login tokens; treat it as a
 * secret. It is also recorded in the `service_tokens` registry (metadata + jti,
 * never the value) so it appears in the operator UI and can be revoked there.
 */

use Nene2\Http\RequestScopedHolder;
use Nene2\Validation\ValidationException;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\ServiceToken\IssueServiceTokenUseCaseInterface;
use NeneInvoice\ServiceToken\ServiceTokenField;

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
$ttl = isset($args['ttl']) && ctype_digit($args['ttl']) ? (int) $args['ttl'] : ServiceTokenField::DEFAULT_TTL_SECONDS;

// Validate exactly like the operator API does (scopes, label/subject length, TTL).
try {
    $input = ServiceTokenField::parse([
        'label'       => $args['label'] ?? 'CLI-issued',
        'scopes'      => $scopes,
        'subject'     => $args['sub'] ?? ServiceTokenField::DEFAULT_SUBJECT,
        'ttl_seconds' => $ttl,
    ]);
} catch (ValidationException $e) {
    foreach ($e->errors() as $error) {
        fwrite(STDERR, sprintf("Error: %s — %s\n", $error->field, $error->message));
    }
    exit(1);
}

$container = (new RuntimeContainerFactory(dirname(__DIR__)))->create();

// The issue use case reads the org from the request-scoped holder (ADR 0006);
// set it explicitly here since there is no HTTP request to resolve it.
$orgHolder = $container->get(ApplicationServiceProvider::ORG_ID_HOLDER);
if (!$orgHolder instanceof RequestScopedHolder) {
    fwrite(STDERR, "Error: org holder service is unavailable.\n");
    exit(1);
}
$orgHolder->set($org);

$useCase = $container->get(IssueServiceTokenUseCaseInterface::class);
if (!$useCase instanceof IssueServiceTokenUseCaseInterface) {
    fwrite(STDERR, "Error: service-token issuer is unavailable.\n");
    exit(1);
}

$result = $useCase->execute(null, $input);

echo $result->plaintextToken . "\n";
