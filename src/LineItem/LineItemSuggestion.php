<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * A line-item suggestion: a distinct description with the default unit price /
 * tax rate to pre-fill when chosen. Sources are the authoritative item master
 * (#323) and past-document history (#315); `source` marks which one, and
 * `usageCount` is how often the description appears in history (0 for a master
 * row never used yet).
 *
 * The defaults are conveniences only — they never override the tax that applies
 * to a given sale; the operator can edit price and rate per line after picking.
 * Money is integer cents; tax rate is basis points.
 */
final readonly class LineItemSuggestion
{
    public function __construct(
        public string $description,
        public int $unitPriceCents,
        public int $taxRateBps,
        public int $usageCount,
        public LineItemSuggestionSource $source = LineItemSuggestionSource::History,
    ) {
    }
}
