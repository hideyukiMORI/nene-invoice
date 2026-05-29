<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Audit\AuditRecorderInterface;

/**
 * Test spy that captures audit recordings instead of persisting them.
 */
final class RecordingAuditRecorder implements AuditRecorderInterface
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(
        ?int $actorUserId,
        ?int $organizationId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
    ): void {
        $this->records[] = [
            'actor_user_id' => $actorUserId,
            'organization_id' => $organizationId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
        ];
    }
}
