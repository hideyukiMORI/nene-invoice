<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

final readonly class AuditRecorder implements AuditRecorderInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
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
        $this->repository->append(new AuditLog(
            action: $action,
            entityType: $entityType,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            entityId: $entityId,
            before: $before,
            after: $after,
        ));
    }
}
