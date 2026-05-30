<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    private const COLUMNS = 'id, actor_user_id, organization_id, action, entity_type, entity_id, before_json, after_json, created_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for read queries
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function append(AuditLog $log): int
    {
        $this->query->execute(
            'INSERT INTO audit_logs (actor_user_id, organization_id, action, entity_type, entity_id, before_json, after_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $log->actorUserId,
                $log->organizationId,
                $log->action,
                $log->entityType,
                $log->entityId,
                self::encode($log->before),
                self::encode($log->after),
                date('Y-m-d H:i:s'),
            ],
        );

        return $this->query->lastInsertId();
    }

    /** @return list<AuditLog> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM audit_logs WHERE organization_id = ? ORDER BY id DESC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): AuditLog => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM audit_logs WHERE organization_id = ?', [$this->orgId->get()]);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /** @param array<string, mixed>|null $value */
    private static function encode(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string|null $json
     * @return array<string, mixed>|null
     */
    private static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): AuditLog
    {
        return new AuditLog(
            action: (string) $row['action'],
            entityType: (string) $row['entity_type'],
            actorUserId: isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            entityId: isset($row['entity_id']) ? (int) $row['entity_id'] : null,
            before: self::decode(isset($row['before_json']) ? (string) $row['before_json'] : null),
            after: self::decode(isset($row['after_json']) ? (string) $row['after_json'] : null),
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
        );
    }
}
