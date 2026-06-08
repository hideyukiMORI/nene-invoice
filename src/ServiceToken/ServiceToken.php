<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Registry record for an issued NeNe Clear service token (ADR 0009).
 *
 * The token itself is a stateless HMAC JWT and is **never** stored — this row
 * holds only metadata plus the `jti` claim, which keys revocation. Reads/writes
 * are scoped to `organization_id` (ADR 0006); the CLI issuer may set
 * `createdBy = null`.
 *
 * @param list<string> $scopes registered {@see \NeneInvoice\ServiceApi\ServiceScope} values
 */
final readonly class ServiceToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $jti,
        public string $subject,
        public string $label,
        public array $scopes,
        public ?int $createdBy,
        public string $createdAt,
        public string $expiresAt,
        public ?string $revokedAt,
    ) {
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
