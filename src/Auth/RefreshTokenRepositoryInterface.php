<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Persistence for rotating refresh tokens (ADR 0014).
 *
 * Lookups are keyed by the SHA-256 hash of the opaque token, which is itself the
 * bearer capability — so these operations are intentionally NOT organization
 * scoped (the refresh endpoint runs before any auth context exists). The calling
 * use case is responsible for re-deriving and verifying the tenant.
 */
interface RefreshTokenRepositoryInterface
{
    public function findByHash(string $tokenHash): ?RefreshToken;

    /** Persists a freshly minted token and returns its row id. */
    public function create(RefreshToken $token): int;

    /** Marks a token as spent (rotated away) so any later presentation is reuse. */
    public function markUsed(int $id, string $usedAt): void;

    /** Revokes every still-live token sharing the family (reuse / logout defense). */
    public function revokeFamily(string $familyId, string $revokedAt): void;

    /** Housekeeping: removes tokens already past their absolute expiry. */
    public function deleteExpired(string $now): int;
}
