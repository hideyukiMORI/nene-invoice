<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

final readonly class ListQuotesResult
{
    /** @param list<Quote> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
