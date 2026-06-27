<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TaxCalculator();
    }

    public function test_empty_document_is_all_zero(): void
    {
        $result = $this->calculator->calculate([]);

        self::assertSame(0, $result->subtotalCents);
        self::assertSame(0, $result->taxCents);
        self::assertSame(0, $result->totalCents);
        self::assertSame([], $result->breakdown);
    }

    public function test_single_rate_10_percent(): void
    {
        // 3 × ¥1,000 = ¥3,000 ; tax 10% = ¥300 ; total ¥3,300
        $result = $this->calculator->calculate([
            new LineItemInput('Widget', 3, 1000, 1000),
        ]);

        self::assertSame(3000, $result->subtotalCents);
        self::assertSame(300, $result->taxCents);
        self::assertSame(3300, $result->totalCents);
        self::assertCount(1, $result->breakdown);
        self::assertSame(1000, $result->breakdown[0]->taxRateBps);
        self::assertSame(3000, $result->breakdown[0]->taxableAmountCents);
        self::assertSame(300, $result->breakdown[0]->taxCents);
    }

    public function test_rounds_once_per_rate_not_per_line(): void
    {
        // Two 8% lines of ¥105 each.
        // Per-line rounding: round(8.4)+round(8.4) = 8+8 = 16 (WRONG).
        // Per-rate (ADR 0004): round(210 × 8%) = round(16.8) = 17 (CORRECT).
        $result = $this->calculator->calculate([
            new LineItemInput('A', 1, 105, 800),
            new LineItemInput('B', 1, 105, 800),
        ]);

        self::assertSame(210, $result->subtotalCents);
        self::assertSame(17, $result->taxCents);
        self::assertSame(227, $result->totalCents);
    }

    public function test_mixed_10_and_8_percent_breakdown(): void
    {
        // 10%: ¥2,000 → tax ¥200 ; 8%: ¥1,500 → tax ¥120
        $result = $this->calculator->calculate([
            new LineItemInput('Standard', 2, 1000, 1000),
            new LineItemInput('Reduced', 1, 1500, 800),
        ]);

        self::assertSame(3500, $result->subtotalCents);
        self::assertSame(320, $result->taxCents);
        self::assertSame(3820, $result->totalCents);

        // Breakdown ordered by ascending rate: 8% then 10%.
        self::assertCount(2, $result->breakdown);
        self::assertSame(800, $result->breakdown[0]->taxRateBps);
        self::assertSame(1500, $result->breakdown[0]->taxableAmountCents);
        self::assertSame(120, $result->breakdown[0]->taxCents);
        self::assertSame(1000, $result->breakdown[1]->taxRateBps);
        self::assertSame(2000, $result->breakdown[1]->taxableAmountCents);
        self::assertSame(200, $result->breakdown[1]->taxCents);
    }

    public function test_half_up_rounding_at_boundary(): void
    {
        // ¥5 × 10% = ¥0.5 → half-up → ¥1
        $result = $this->calculator->calculate([
            new LineItemInput('Tiny', 1, 5, 1000),
        ]);

        self::assertSame(1, $result->taxCents);
    }

    /**
     * Half-up rounding flips just-below vs just-above the tie point, for both
     * statutory rates (ADR 0004). 10% has exact .5-cent ties (at 5¢, 15¢);
     * 8% never lands exactly on .5 so the flip sits between two integers.
     */
    #[DataProvider('roundingBoundaryCases')]
    public function test_half_up_rounding_boundary(int $unitPriceCents, int $rateBps, int $expectedTaxCents): void
    {
        $result = $this->calculator->calculate([
            new LineItemInput('X', 1, $unitPriceCents, $rateBps),
        ]);

        self::assertSame($expectedTaxCents, $result->taxCents);
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function roundingBoundaryCases(): iterable
    {
        // 10% (1000 bps): flip 0→1 at the 0.5¢ tie (5¢); the tie rounds up.
        yield '10% 4c -> 0 (below half)'  => [4, 1000, 0];
        yield '10% 5c -> 1 (exact half)'  => [5, 1000, 1];
        yield '10% 6c -> 1 (above half)'  => [6, 1000, 1];
        yield '10% 14c -> 1 (below 1.5)'  => [14, 1000, 1];
        yield '10% 15c -> 2 (exact 1.5)'  => [15, 1000, 2];
        // 8% (800 bps): flip 0→1 between 6¢ (0.48) and 7¢ (0.56) — no exact tie.
        yield '8% 6c -> 0 (below half)'   => [6, 800, 0];
        yield '8% 7c -> 1 (above half)'   => [7, 800, 1];
        // 8% flip 1→2 between 18¢ (1.44) and 19¢ (1.52).
        yield '8% 18c -> 1 (below 1.5)'   => [18, 800, 1];
        yield '8% 19c -> 2 (above 1.5)'   => [19, 800, 2];
    }

    public function test_zero_amount_line_contributes_zero_tax(): void
    {
        // A zero-priced line is valid input (use cases reject only negatives).
        $result = $this->calculator->calculate([
            new LineItemInput('Free sample', 1, 0, 1000),
        ]);

        self::assertSame(0, $result->subtotalCents);
        self::assertSame(0, $result->taxCents);
        self::assertSame(0, $result->totalCents);
        self::assertCount(1, $result->breakdown);
        self::assertSame(0, $result->breakdown[0]->taxCents);
    }

    public function test_zero_line_mixed_with_non_zero_line(): void
    {
        // The zero line joins its rate group without changing the rounded tax.
        $result = $this->calculator->calculate([
            new LineItemInput('Free', 1, 0, 1000),
            new LineItemInput('Paid', 1, 1000, 1000),
        ]);

        self::assertSame(1000, $result->subtotalCents);
        self::assertSame(100, $result->taxCents);
        self::assertSame(1100, $result->totalCents);
        self::assertCount(1, $result->breakdown);
    }

    public function test_large_amount_does_not_overflow(): void
    {
        // ¥999,999,999 × 10% = ¥99,999,999.9 → half-up → ¥100,000,000.
        $result = $this->calculator->calculate([
            new LineItemInput('Big', 1, 999_999_999, 1000),
        ]);

        self::assertSame(999_999_999, $result->subtotalCents);
        self::assertSame(100_000_000, $result->taxCents);
        self::assertSame(1_099_999_999, $result->totalCents);
    }
}
