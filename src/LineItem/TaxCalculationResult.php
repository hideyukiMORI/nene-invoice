<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * The single source of truth for a document's monetary totals. The API response
 * and the PDF both render these exact values; neither recalculates (ADR 0004,
 * accounting-compliance.md).
 */
final readonly class TaxCalculationResult
{
    /** @param list<TaxBreakdownLine> $breakdown ordered by ascending tax rate */
    public function __construct(
        public int $subtotalCents,
        public int $taxCents,
        public int $totalCents,
        public array $breakdown,
    ) {
    }
}
