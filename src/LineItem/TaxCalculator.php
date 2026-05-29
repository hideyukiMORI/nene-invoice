<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Computes consumption tax for a set of line items.
 *
 * Per ADR 0004 / accounting-compliance.md §3, tax is rounded **once per tax
 * rate per document** — never per line. Line subtotals are summed per rate
 * first, then a single half-up rounding is applied to each rate group. All
 * arithmetic is integer-only (no floats for money).
 */
final class TaxCalculator
{
    /** @param list<LineItemInput> $lines */
    public function calculate(array $lines): TaxCalculationResult
    {
        // Sum taxable amounts per rate, preserving first-seen order, then sort by rate.
        /** @var array<int, int> $taxableByRate */
        $taxableByRate = [];

        foreach ($lines as $line) {
            $taxableByRate[$line->taxRateBps] = ($taxableByRate[$line->taxRateBps] ?? 0) + $line->lineSubtotalCents();
        }

        ksort($taxableByRate);

        $breakdown = [];
        $subtotalCents = 0;
        $taxCents = 0;

        foreach ($taxableByRate as $rateBps => $taxableAmountCents) {
            $rateTaxCents = self::roundHalfUp($taxableAmountCents, $rateBps);

            $breakdown[] = new TaxBreakdownLine($rateBps, $taxableAmountCents, $rateTaxCents);
            $subtotalCents += $taxableAmountCents;
            $taxCents += $rateTaxCents;
        }

        return new TaxCalculationResult(
            subtotalCents: $subtotalCents,
            taxCents: $taxCents,
            totalCents: $subtotalCents + $taxCents,
            breakdown: $breakdown,
        );
    }

    /**
     * Half-up rounding of `taxableAmountCents * rateBps / 10000`, integer-only.
     * Valid for non-negative amounts (billing amounts are non-negative).
     */
    private static function roundHalfUp(int $taxableAmountCents, int $rateBps): int
    {
        return intdiv($taxableAmountCents * $rateBps + 5000, 10000);
    }
}
