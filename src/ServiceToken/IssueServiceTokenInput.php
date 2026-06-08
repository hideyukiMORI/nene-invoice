<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Validated payload for issuing a service token.
 *
 * @param list<string> $scopes registered {@see \NeneInvoice\ServiceApi\ServiceScope} values
 */
final readonly class IssueServiceTokenInput
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $label,
        public array $scopes,
        public string $subject,
        public int $ttlSeconds,
    ) {
    }
}
