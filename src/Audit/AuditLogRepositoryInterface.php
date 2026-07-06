<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Read side of the audit trail (ADR 0008 / ADR 0014).
 *
 * Persistence is the framework's append-only `Nene2\Audit\PdoAuditEventRepository`;
 * this contract owns the two read concerns the framework contract intentionally
 * leaves to the product: organization scoping (tenant isolation, ADR 0006) and
 * actor-email resolution. Reads are always constrained to the organization held
 * in the request-scoped org holder. There is no write method here — mutating use
 * cases record `Nene2\Audit\AuditEvent` directly through the framework recorder.
 */
interface AuditLogRepositoryInterface
{
    /** @return list<AuditLog> */
    public function findAll(AuditLogFilter $filter, int $limit, int $offset): array;

    public function count(AuditLogFilter $filter): int;
}
