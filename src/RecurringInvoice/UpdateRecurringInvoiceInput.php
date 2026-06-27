<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use NeneInvoice\LineItem\LineItemInput;

final readonly class UpdateRecurringInvoiceInput
{
    /** @param list<LineItemInput> $lines */
    public function __construct(
        public int $clientId,
        public string $name,
        public RecurringFrequency $frequency,
        public string $nextRunOn,
        public array $lines,
        public bool $isActive,
        public ?string $notes = null,
    ) {
    }
}
