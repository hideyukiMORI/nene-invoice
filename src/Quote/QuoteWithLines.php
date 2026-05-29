<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\LineItem\LineItem;

final readonly class QuoteWithLines
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public Quote $quote,
        public array $lines,
    ) {
    }
}
