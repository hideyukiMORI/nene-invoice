<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private const COLUMNS = 'id, user_id, organization_id, family_id, token_hash, issued_at, expires_at, used_at, revoked_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM refresh_tokens WHERE token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function create(RefreshToken $token): int
    {
        $this->query->execute(
            'INSERT INTO refresh_tokens
                (user_id, organization_id, family_id, token_hash, issued_at, expires_at, used_at, revoked_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $token->userId,
                $token->organizationId,
                $token->familyId,
                $token->tokenHash,
                $token->issuedAt,
                $token->expiresAt,
                $token->usedAt,
                $token->revokedAt,
                date('Y-m-d H:i:s'),
            ],
        );

        return $this->query->lastInsertId();
    }

    public function markUsed(int $id, string $usedAt): void
    {
        $this->query->execute(
            'UPDATE refresh_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [$usedAt, $id],
        );
    }

    public function revokeFamily(string $familyId, string $revokedAt): void
    {
        $this->query->execute(
            'UPDATE refresh_tokens SET revoked_at = ? WHERE family_id = ? AND revoked_at IS NULL',
            [$revokedAt, $familyId],
        );
    }

    public function deleteExpired(string $now): int
    {
        return $this->query->execute(
            'DELETE FROM refresh_tokens WHERE expires_at < ?',
            [$now],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): RefreshToken
    {
        return new RefreshToken(
            userId: (int) $row['user_id'],
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            familyId: (string) $row['family_id'],
            tokenHash: (string) $row['token_hash'],
            issuedAt: (string) $row['issued_at'],
            expiresAt: (string) $row['expires_at'],
            usedAt: isset($row['used_at']) ? (string) $row['used_at'] : null,
            revokedAt: isset($row['revoked_at']) ? (string) $row['revoked_at'] : null,
            id: (int) $row['id'],
        );
    }
}
