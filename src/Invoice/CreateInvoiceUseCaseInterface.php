<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface CreateInvoiceUseCaseInterface
{
    public function execute(?int $actorUserId, CreateInvoiceInput $input): InvoiceWithLines;
}
