<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Lists the audit trail for an organization, most recent first.
 */
final readonly class ListAuditLogsUseCase
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
    ) {
    }

    public function execute(int $organizationId, int $limit, int $offset): ListAuditLogsResult
    {
        return new ListAuditLogsResult(
            $this->logs->findByOrganization($organizationId, $limit, $offset),
            $this->logs->countByOrganization($organizationId),
        );
    }
}
