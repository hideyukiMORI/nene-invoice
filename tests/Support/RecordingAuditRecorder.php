<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Test spy for the framework audit recorder (ADR 0014). Doubles both the
 * transaction-atomic {@see AuditRecorderFactoryInterface} (its {@see forExecutor()}
 * returns itself) and the {@see AuditRecorderInterface}, so use cases that inject
 * either shape can capture recordings without a database. Each recorded
 * {@see AuditEvent} is flattened into the same array shape the previous spy
 * exposed, so existing assertions on `$recorder->records[n][...]` are unchanged.
 */
final class RecordingAuditRecorder implements AuditRecorderFactoryInterface, AuditRecorderInterface
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function forExecutor(DatabaseQueryExecutorInterface $executor): AuditRecorderInterface
    {
        return $this;
    }

    public function record(AuditEvent $event): void
    {
        $this->records[] = [
            'actor_user_id' => $event->actorId,
            'organization_id' => $event->organizationId,
            'action' => $event->action,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'before' => $event->before,
            'after' => $event->after,
        ];
    }
}
