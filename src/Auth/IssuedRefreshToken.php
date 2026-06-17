<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * The plaintext refresh token to hand back to the client (via `Set-Cookie`)
 * together with its absolute expiry. The plaintext exists only in transit; the
 * persisted record keeps just the hash.
 */
final readonly class IssuedRefreshToken
{
    public function __construct(
        public string $rawToken,
        public string $expiresAt,
        public int $expiresAtTimestamp,
    ) {
    }
}
