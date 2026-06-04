<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

final readonly class ListQuotesResult
{
    /**
     * @param list<Quote> $items
     * @param array<int, string> $clientNameByQuoteId quote id => client display name
     */
    public function __construct(
        public array $items,
        public int $total,
        public array $clientNameByQuoteId = [],
    ) {
    }
}
