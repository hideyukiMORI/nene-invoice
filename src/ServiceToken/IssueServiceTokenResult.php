<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Outcome of issuing a service token: the persisted registry record plus the
 * **plaintext token**, which is returned exactly once (never stored) and must be
 * shown to the operator immediately.
 */
final readonly class IssueServiceTokenResult
{
    public function __construct(
        public ServiceToken $token,
        public string $plaintextToken,
    ) {
    }
}
