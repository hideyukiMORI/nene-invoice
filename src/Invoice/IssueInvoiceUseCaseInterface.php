<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface IssueInvoiceUseCaseInterface
{
    public function execute(?int $actorUserId, int $id, IssueInvoiceInput $input): InvoiceWithLines;
}
