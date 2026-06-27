<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use NeneInvoice\LineItem\LineItemInput;

final readonly class CreateRecurringInvoiceInput
{
    /** @param list<LineItemInput> $lines */
    public function __construct(
        public int $clientId,
        public string $name,
        public RecurringFrequency $frequency,
        public string $firstRunOn,
        public array $lines,
        public bool $isActive = true,
        public ?string $notes = null,
    ) {
    }
}
