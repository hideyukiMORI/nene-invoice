<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nene2\Http\ClockInterface;

final readonly class AuditRecorder implements AuditRecorderInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function record(
        ?int $actorUserId,
        ?int $organizationId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
    ): void {
        // The audit timestamp comes from the injected clock (UTC), so it is
        // deterministic in tests and consistent with every other "now" (ADR 0010).
        $this->repository->append(new AuditLog(
            action: $action,
            entityType: $entityType,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            entityId: $entityId,
            before: $before,
            after: $after,
            createdAt: $this->clock->now()->format('Y-m-d H:i:s'),
        ));
    }
}
