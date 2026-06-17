<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * A persisted refresh-token record (ADR 0014). The plaintext token is never
 * stored; {@see $tokenHash} holds its SHA-256 digest. A token is spendable only
 * while `usedAt` and `revokedAt` are both null and it has not expired.
 */
final readonly class RefreshToken
{
    public function __construct(
        public int $userId,
        public ?int $organizationId,
        public string $familyId,
        public string $tokenHash,
        public string $issuedAt,
        public string $expiresAt,
        public ?string $usedAt = null,
        public ?string $revokedAt = null,
        public ?int $id = null,
    ) {
    }

    /** True once the token has been rotated away (spent) or explicitly revoked. */
    public function isConsumed(): bool
    {
        return $this->usedAt !== null || $this->revokedAt !== null;
    }

    public function isExpired(string $now): bool
    {
        return $this->expiresAt < $now;
    }
}
