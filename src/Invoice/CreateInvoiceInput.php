<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\LineItem\LineItemInput;

final readonly class CreateInvoiceInput
{
    /** @param list<LineItemInput> $lines */
    public function __construct(
        public int $clientId,
        public array $lines,
        public ?string $notes = null,
    ) {
    }
}
