<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Admin read filters for the quote list. Every predicate is optional; the empty
 * filter lists everything. Statuses are an OR-set of status values.
 */
final readonly class QuoteListFilter
{
    /**
     * @param list<string> $statuses subset of status values; empty = any
     */
    public function __construct(
        public array $statuses = [],
        /** Search: matches quote_number OR client name (substring). */
        public ?string $search = null,
        /** valid_until range (YYYY-MM-DD, inclusive). */
        public ?string $validFrom = null,
        public ?string $validTo = null,
        /** total-amount range (integer cents, inclusive). */
        public ?int $totalMin = null,
        public ?int $totalMax = null,
    ) {
    }
}
