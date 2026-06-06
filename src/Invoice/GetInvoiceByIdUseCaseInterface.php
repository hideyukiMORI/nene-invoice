<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface GetInvoiceByIdUseCaseInterface
{
    public function execute(int $id): InvoiceWithLines;
}
