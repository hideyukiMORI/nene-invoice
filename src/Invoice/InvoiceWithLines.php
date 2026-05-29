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
        /** total_cents − Σ valid payments; 0 unless the read path computes it. */
        public int $outstandingCents = 0,
    ) {
    }
}
