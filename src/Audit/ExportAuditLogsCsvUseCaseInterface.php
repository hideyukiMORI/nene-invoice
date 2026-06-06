<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

interface ExportAuditLogsCsvUseCaseInterface
{
    public function execute(AuditLogFilter $filter): string;
}
