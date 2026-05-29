<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Append-only persistence for the audit trail.
 */
interface AuditLogRepositoryInterface
{
    public function append(AuditLog $log): int;

    /** @return list<AuditLog> */
    public function findByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;
}
