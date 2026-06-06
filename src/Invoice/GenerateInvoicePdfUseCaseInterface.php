<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Invoice\Pdf\InvoicePdfData;

interface GenerateInvoicePdfUseCaseInterface
{
    public function execute(int $invoiceId): InvoicePdfData;
}
