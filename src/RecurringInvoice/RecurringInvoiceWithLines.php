<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use NeneInvoice\LineItem\LineItem;

final readonly class RecurringInvoiceWithLines
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public RecurringInvoice $schedule,
        public array $lines,
    ) {
    }
}
