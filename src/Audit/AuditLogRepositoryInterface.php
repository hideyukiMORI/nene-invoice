<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Append-only persistence for the audit trail.
 *
 * Reads are scoped to the organization held in the request-scoped org holder
 * (ADR 0006). {@see append()} keeps the organization on the {@see AuditLog}
 * itself: writes also run on holder-less paths (e.g. superadmin organization
 * provisioning), so the write side must not depend on the holder.
 */
interface AuditLogRepositoryInterface
{
    public function append(AuditLog $log): int;

    /** @return list<AuditLog> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;
}
