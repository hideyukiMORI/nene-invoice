<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

interface ListAuditLogsUseCaseInterface
{
    public function execute(AuditLogFilter $filter, int $limit, int $offset): ListAuditLogsResult;
}
