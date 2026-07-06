<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditQuery;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

/**
 * Read side of the audit trail (ADR 0014). Persistence is the framework's
 * {@see AuditEventRepositoryInterface} (`PdoAuditEventRepository`, pointed at
 * Invoice's `audit_logs` table by the product {@see \Nene2\Audit\AuditTableConfig}).
 * This class adds the two product-specific read concerns the framework contract
 * leaves out:
 *
 * - **organization scoping** — every read is constrained to the org in the
 *   request-scoped holder (ADR 0006), mapped onto {@see AuditQuery::$organizationId};
 * - **actor email** — resolved with a single `users` lookup and attached to the
 *   {@see AuditLog} view, never persisted on the trail.
 *
 * The query is sorted by `id DESC` (via {@see AuditQuery} sort column `id`), which
 * reproduces the previous hand-rolled `ORDER BY a.id DESC` byte-for-byte so the
 * list JSON and CSV export order is unchanged.
 */
final readonly class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for read queries
     */
    public function __construct(
        private AuditEventRepositoryInterface $events,
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @return list<AuditLog> */
    public function findAll(AuditLogFilter $filter, int $limit, int $offset): array
    {
        $events = $this->events->query($this->toQuery($filter), $limit, $offset);
        $emails = $this->resolveActorEmails($events);

        return array_map(
            static fn (AuditEvent $event): AuditLog => new AuditLog(
                action: $event->action,
                entityType: $event->entityType,
                actorUserId: $event->actorId !== null ? (int) $event->actorId : null,
                organizationId: $event->organizationId !== null ? (int) $event->organizationId : null,
                entityId: $event->entityId !== null ? (int) $event->entityId : null,
                before: $event->before,
                after: $event->after,
                id: $event->id !== null ? (int) $event->id : null,
                createdAt: $event->occurredAt,
                actorEmail: $event->actorId !== null ? ($emails[(int) $event->actorId] ?? null) : null,
            ),
            $events,
        );
    }

    public function count(AuditLogFilter $filter): int
    {
        return $this->events->count($this->toQuery($filter));
    }

    private function toQuery(AuditLogFilter $filter): AuditQuery
    {
        return new AuditQuery(
            organizationId: $this->orgId->get(),
            entityType: $filter->entityType,
            action: $filter->action,
            actorId: $filter->actorUserId,
            occurredFrom: $filter->createdFrom,
            occurredTo: $filter->createdTo,
            // `id DESC` reproduces the previous hand-rolled ordering exactly.
            sortColumn: 'id',
            sortDirection: 'DESC',
        );
    }

    /**
     * @param list<AuditEvent> $events
     * @return array<int, string>
     */
    private function resolveActorEmails(array $events): array
    {
        $ids = [];

        foreach ($events as $event) {
            if ($event->actorId !== null) {
                $ids[(int) $event->actorId] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $keys = array_keys($ids);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $rows = $this->query->fetchAll(
            'SELECT id, email FROM users WHERE id IN (' . $placeholders . ')',
            $keys,
        );

        $emails = [];

        foreach ($rows as $row) {
            $emails[(int) $row['id']] = (string) $row['email'];
        }

        return $emails;
    }
}
