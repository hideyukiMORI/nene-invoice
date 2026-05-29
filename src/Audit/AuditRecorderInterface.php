<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Records a mutating operation in the audit trail (ADR 0008). Use cases call
 * this after a successful create / update / delete.
 */
interface AuditRecorderInterface
{
    /**
     * @param array<string, mixed>|null $before sanitized snapshot before the change (null for create)
     * @param array<string, mixed>|null $after  sanitized snapshot after the change (null for delete)
     */
    public function record(
        ?int $actorUserId,
        ?int $organizationId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
    ): void;
}
