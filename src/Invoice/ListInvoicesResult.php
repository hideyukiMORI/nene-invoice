<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

final readonly class ListInvoicesResult
{
    /** @param list<Invoice> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
