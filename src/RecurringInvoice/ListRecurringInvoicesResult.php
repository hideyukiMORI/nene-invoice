<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

final readonly class ListRecurringInvoicesResult
{
    /** @param list<RecurringInvoice> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
