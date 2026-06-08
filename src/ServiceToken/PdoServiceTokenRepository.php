<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

/**
 * SQL-backed service-token registry, scoped to the resolved organization
 * (ADR 0006). `scopes` is stored as a comma-separated string.
 */
final readonly class PdoServiceTokenRepository implements ServiceTokenRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, jti, subject, label, scopes, created_by, created_at, expires_at, revoked_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for scoping
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function save(ServiceToken $token): int
    {
        $this->query->execute(
            'INSERT INTO service_tokens (organization_id, jti, subject, label, scopes, created_by, created_at, expires_at, revoked_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->orgId->get(),
                $token->jti,
                $token->subject,
                $token->label,
                implode(',', $token->scopes),
                $token->createdBy,
                $token->createdAt,
                $token->expiresAt,
                $token->revokedAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $id): ?ServiceToken
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM service_tokens WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row === null ? null : self::mapRow($row);
    }

    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM service_tokens WHERE organization_id = ? ORDER BY id DESC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(static fn (array $row): ServiceToken => self::mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS total FROM service_tokens WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        return $row === null ? 0 : (int) $row['total'];
    }

    public function revoke(int $id, string $revokedAt): void
    {
        $affected = $this->query->execute(
            'UPDATE service_tokens SET revoked_at = ? WHERE id = ? AND organization_id = ? AND revoked_at IS NULL',
            [$revokedAt, $id, $this->orgId->get()],
        );

        if ($affected === 0 && $this->findById($id) === null) {
            throw new ServiceTokenNotFoundException($id);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function mapRow(array $row): ServiceToken
    {
        $scopes = array_values(array_filter(
            explode(',', (string) $row['scopes']),
            static fn (string $s): bool => $s !== '',
        ));

        return new ServiceToken(
            id: (int) $row['id'],
            organizationId: (int) $row['organization_id'],
            jti: (string) $row['jti'],
            subject: (string) $row['subject'],
            label: (string) $row['label'],
            scopes: $scopes,
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
            createdAt: (string) $row['created_at'],
            expiresAt: (string) $row['expires_at'],
            revokedAt: $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
        );
    }
}
