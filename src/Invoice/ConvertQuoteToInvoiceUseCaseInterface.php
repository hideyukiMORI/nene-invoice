<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface ConvertQuoteToInvoiceUseCaseInterface
{
    public function execute(?int $actorUserId, int $quoteId): InvoiceWithLines;
}
