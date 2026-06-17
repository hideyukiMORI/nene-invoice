<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Auth\RefreshToken;
use NeneInvoice\Auth\RefreshTokenRepositoryInterface;

/**
 * In-memory refresh-token store for use-case tests. Mirrors the PDO repository's
 * semantics: hash lookup, sequential ids, used/revoked marking, and family-wide
 * revocation.
 */
final class InMemoryRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /** @var array<int, RefreshToken> */
    private array $rows = [];

    private int $nextId = 1;

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        foreach ($this->rows as $row) {
            if ($row->tokenHash === $tokenHash) {
                return $row;
            }
        }

        return null;
    }

    public function create(RefreshToken $token): int
    {
        $id = $this->nextId++;

        $this->rows[$id] = new RefreshToken(
            userId: $token->userId,
            organizationId: $token->organizationId,
            familyId: $token->familyId,
            tokenHash: $token->tokenHash,
            issuedAt: $token->issuedAt,
            expiresAt: $token->expiresAt,
            usedAt: $token->usedAt,
            revokedAt: $token->revokedAt,
            id: $id,
        );

        return $id;
    }

    public function markUsed(int $id, string $usedAt): void
    {
        $row = $this->rows[$id] ?? null;

        if ($row === null || $row->usedAt !== null) {
            return;
        }

        $this->rows[$id] = $this->with($row, usedAt: $usedAt);
    }

    public function revokeFamily(string $familyId, string $revokedAt): void
    {
        foreach ($this->rows as $id => $row) {
            if ($row->familyId === $familyId && $row->revokedAt === null) {
                $this->rows[$id] = $this->with($row, revokedAt: $revokedAt);
            }
        }
    }

    public function deleteExpired(string $now): int
    {
        $deleted = 0;
        foreach ($this->rows as $id => $row) {
            if ($row->expiresAt < $now) {
                unset($this->rows[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function with(RefreshToken $row, ?string $usedAt = null, ?string $revokedAt = null): RefreshToken
    {
        return new RefreshToken(
            userId: $row->userId,
            organizationId: $row->organizationId,
            familyId: $row->familyId,
            tokenHash: $row->tokenHash,
            issuedAt: $row->issuedAt,
            expiresAt: $row->expiresAt,
            usedAt: $usedAt ?? $row->usedAt,
            revokedAt: $revokedAt ?? $row->revokedAt,
            id: $row->id,
        );
    }
}
