<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

interface ListInvoicesUseCaseInterface
{
    public function execute(int $limit, int $offset, ?InvoiceListFilter $filter = null): ListInvoicesResult;

    public function executeAdmin(InvoiceListFilter $filter, InvoiceSort $sort, int $limit, int $offset): ListInvoicesResult;
}
