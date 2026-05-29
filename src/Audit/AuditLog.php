<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * One recorded mutating operation (ADR 0008).
 *
 * `before` / `after` are sanitized snapshots (no secrets) of the affected entity.
 * `before` is null for creates; `after` is null for deletes.
 */
final readonly class AuditLog
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public string $action,
        public string $entityType,
        public ?int $actorUserId = null,
        public ?int $organizationId = null,
        public ?int $entityId = null,
        public ?array $before = null,
        public ?array $after = null,
        public ?int $id = null,
        public ?string $createdAt = null,
    ) {
    }
}
