<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * One row of the admin invoice list: the invoice plus its client's display name
 * (resolved by the list query's join, so the UI need not look it up).
 */
final readonly class InvoiceListRow
{
    public function __construct(
        public Invoice $invoice,
        public string $clientName,
    ) {
    }
}
