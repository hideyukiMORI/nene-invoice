<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\LineItem\LineItem;

final readonly class InvoiceWithLines
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public Invoice $invoice,
        public array $lines,
    ) {
    }
}
