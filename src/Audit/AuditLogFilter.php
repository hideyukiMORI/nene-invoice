<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Read-side filter for the audit trail. Every field is optional; a null field
 * does not constrain the query. Organization scoping is applied separately by
 * the repository (request-scoped holder, ADR 0006) and is never part of this
 * filter.
 *
 * `createdFrom` / `createdTo` are inclusive `Y-m-d H:i:s` bounds on `created_at`.
 */
final readonly class AuditLogFilter
{
    public function __construct(
        public ?string $entityType = null,
        public ?string $action = null,
        public ?int $actorUserId = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
    ) {
    }
}
