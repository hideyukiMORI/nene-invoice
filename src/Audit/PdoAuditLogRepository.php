<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    private const COLUMNS = 'a.id, a.actor_user_id, a.organization_id, a.action, a.entity_type, a.entity_id, a.before_json, a.after_json, a.created_at';

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
                $log->createdAt ?? date('Y-m-d H:i:s'),
            ],
        );

        return $this->query->lastInsertId();
    }

    /** @return list<AuditLog> */
    public function findAll(AuditLogFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filter);
        $params[] = $limit;
        $params[] = $offset;

        // Resolve the actor's current email for display (the stored
        // actor_user_id is immutable; this lookup is read-time only).
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ', u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE ' . $where . ' ORDER BY a.id DESC LIMIT ? OFFSET ?',
            $params,
        );

        return array_map(fn (array $row): AuditLog => $this->mapRow($row), $rows);
    }

    public function count(AuditLogFilter $filter): int
    {
        [$where, $params] = $this->buildWhere($filter);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM audit_logs a WHERE ' . $where, $params);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * Builds the shared WHERE clause (organization scope + optional filters).
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(AuditLogFilter $filter): array
    {
        // Columns are qualified with the `a` (audit_logs) alias because findAll
        // joins `users` (both tables have organization_id / created_at).
        $conditions = ['a.organization_id = ?'];
        $params = [$this->orgId->get()];

        if ($filter->entityType !== null) {
            $conditions[] = 'a.entity_type = ?';
            $params[] = $filter->entityType;
        }

        if ($filter->action !== null) {
            $conditions[] = 'a.action = ?';
            $params[] = $filter->action;
        }

        if ($filter->actorUserId !== null) {
            $conditions[] = 'a.actor_user_id = ?';
            $params[] = $filter->actorUserId;
        }

        if ($filter->createdFrom !== null) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filter->createdFrom;
        }

        if ($filter->createdTo !== null) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $filter->createdTo;
        }

        return [implode(' AND ', $conditions), $params];
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
            actorEmail: isset($row['actor_email']) ? (string) $row['actor_email'] : null,
        );
    }
}
