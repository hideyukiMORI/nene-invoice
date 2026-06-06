<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface SendInvoiceEmailUseCaseInterface
{
    public function execute(?int $actorUserId, int $invoiceId): void;
}
