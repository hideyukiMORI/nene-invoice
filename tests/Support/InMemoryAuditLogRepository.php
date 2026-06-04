<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditLog;
use NeneInvoice\Audit\AuditLogFilter;
use NeneInvoice\Audit\AuditLogRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Returns logs most-recent-first
 * (descending id), mirroring PdoAuditLogRepository. Reads are scoped to the
 * request-scoped org holder (defaulting to organization 1); {@see append()}
 * keeps the organization on the log itself, like the Pdo repository.
 */
final class InMemoryAuditLogRepository implements AuditLogRepositoryInterface
{
    /** @var array<int, AuditLog> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function append(AuditLog $log): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new AuditLog(
            action: $log->action,
            entityType: $log->entityType,
            actorUserId: $log->actorUserId,
            organizationId: $log->organizationId,
            entityId: $log->entityId,
            before: $log->before,
            after: $log->after,
            id: $id,
            createdAt: '2026-05-29 00:00:00',
        );

        return $id;
    }

    /** @return list<AuditLog> */
    public function findAll(AuditLogFilter $filter, int $limit, int $offset): array
    {
        $matches = $this->matching($filter);

        usort($matches, static fn (AuditLog $a, AuditLog $b): int => ($b->id ?? 0) <=> ($a->id ?? 0));

        return array_slice($matches, $offset, $limit);
    }

    public function count(AuditLogFilter $filter): int
    {
        return count($this->matching($filter));
    }

    /** @return list<AuditLog> */
    private function matching(AuditLogFilter $filter): array
    {
        $organizationId = $this->orgId->get();

        return array_values(array_filter(
            $this->byId,
            static function (AuditLog $log) use ($organizationId, $filter): bool {
                if ($log->organizationId !== $organizationId) {
                    return false;
                }
                if ($filter->entityType !== null && $log->entityType !== $filter->entityType) {
                    return false;
                }
                if ($filter->action !== null && $log->action !== $filter->action) {
                    return false;
                }
                if ($filter->actorUserId !== null && $log->actorUserId !== $filter->actorUserId) {
                    return false;
                }
                if ($filter->createdFrom !== null && ($log->createdAt ?? '') < $filter->createdFrom) {
                    return false;
                }
                if ($filter->createdTo !== null && ($log->createdAt ?? '') > $filter->createdTo) {
                    return false;
                }

                return true;
            },
        ));
    }
}
