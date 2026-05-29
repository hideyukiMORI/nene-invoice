<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

final readonly class ListInvoicesResult
{
    /**
     * @param list<Invoice> $items
     * @param array<int, int> $outstandingByInvoiceId invoice id => outstanding cents
     */
    public function __construct(
        public array $items,
        public int $total,
        public array $outstandingByInvoiceId = [],
    ) {
    }
}
