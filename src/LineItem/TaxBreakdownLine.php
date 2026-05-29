<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Per-tax-rate breakdown: the taxable amount and the consumption tax for one
 * rate. These are the 税率ごとの対価の額 / 税率ごとの消費税額 a qualified
 * invoice must display.
 */
final readonly class TaxBreakdownLine
{
    public function __construct(
        public int $taxRateBps,
        public int $taxableAmountCents,
        public int $taxCents,
    ) {
    }
}
