<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

/**
 * Lists the audit trail for the resolved organization, most recent first. The
 * organization is read from the request-scoped org holder by the repository.
 */
final readonly class ListAuditLogsUseCase implements ListAuditLogsUseCaseInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
    ) {
    }

    public function execute(AuditLogFilter $filter, int $limit, int $offset): ListAuditLogsResult
    {
        return new ListAuditLogsResult(
            $this->logs->findAll($filter, $limit, $offset),
            $this->logs->count($filter),
        );
    }
}
