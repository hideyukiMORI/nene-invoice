<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\LineItem;

use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\TaxCalculator;
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
}
