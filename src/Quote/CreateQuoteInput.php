<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\LineItem\LineItemInput;

final readonly class CreateQuoteInput
{
    /** @param list<LineItemInput> $lines */
    public function __construct(
        public int $clientId,
        public array $lines,
        public ?string $validUntil = null,
        public ?string $notes = null,
    ) {
    }
}
