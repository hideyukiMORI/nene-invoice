<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Audit\AuditLog;
use NeneInvoice\Audit\AuditLogRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Returns logs most-recent-first
 * (descending id), mirroring PdoAuditLogRepository.
 */
final class InMemoryAuditLogRepository implements AuditLogRepositoryInterface
{
    /** @var array<int, AuditLog> */
    private array $byId = [];
    private int $nextId = 1;

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
    public function findByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (AuditLog $log): bool => $log->organizationId === $organizationId,
        ));

        usort($matches, static fn (AuditLog $a, AuditLog $b): int => ($b->id ?? 0) <=> ($a->id ?? 0));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (AuditLog $log): bool => $log->organizationId === $organizationId,
        ));
    }
}
