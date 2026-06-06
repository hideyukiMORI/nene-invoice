<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * A history-based line-item suggestion (#315): a distinct description the org has
 * used before, with the default unit price / tax rate to pre-fill when chosen.
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
    ) {
    }
}
