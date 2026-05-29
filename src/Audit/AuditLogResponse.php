<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Serializes an {@see AuditLog} to its snake_case JSON representation. `before`
 * and `after` are the already-sanitized snapshots stored at record time.
 */
final class AuditLogResponse
{
    /** @return array<string, mixed> */
    public static function toArray(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor_user_id' => $log->actorUserId,
            'organization_id' => $log->organizationId,
            'action' => $log->action,
            'entity_type' => $log->entityType,
            'entity_id' => $log->entityId,
            'before' => $log->before,
            'after' => $log->after,
            'created_at' => $log->createdAt,
        ];
    }
}
