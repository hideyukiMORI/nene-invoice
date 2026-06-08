<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * SQL-backed revocation check. A `jti` is active iff a registry row exists for
 * it and `revoked_at` is null. Not org-scoped by design (see the interface).
 */
final readonly class PdoServiceTokenAuthorizer implements ServiceTokenAuthorizerInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function isActive(string $jti): bool
    {
        $row = $this->query->fetchOne(
            'SELECT 1 AS ok FROM service_tokens WHERE jti = ? AND revoked_at IS NULL',
            [$jti],
        );

        return $row !== null;
    }
}
