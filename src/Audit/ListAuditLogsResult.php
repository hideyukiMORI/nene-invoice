<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * A page of audit logs plus the total count for the organization.
 */
final readonly class ListAuditLogsResult
{
    /** @param list<AuditLog> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
