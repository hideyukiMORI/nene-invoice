<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface ExportInvoicesCsvUseCaseInterface
{
    public function execute(InvoiceListFilter $filter): string;
}
