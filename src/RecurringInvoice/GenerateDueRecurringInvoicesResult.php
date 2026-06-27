<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * Outcome of one generation run (#503): which schedules produced a draft invoice.
 */
final readonly class GenerateDueRecurringInvoicesResult
{
    /** @param list<array{recurring_invoice_id: int, invoice_id: int}> $generated */
    public function __construct(
        public array $generated,
    ) {
    }

    public function count(): int
    {
        return count($this->generated);
    }
}
