<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * One row of the admin quote list: the quote plus its client's display name
 * (resolved by the list query's join).
 */
final readonly class QuoteListRow
{
    public function __construct(
        public Quote $quote,
        public string $clientName,
    ) {
    }
}
