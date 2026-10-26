<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Template;

use Nene2\Validation\ValidationException;
use NeneInvoice\Template\TemplateField;
use PHPUnit\Framework\TestCase;

final class TemplateFieldTest extends TestCase
{
    public function test_accepts_a_discount_line_preset(): void
    {
        $parsed = TemplateField::parse([
            'name' => '値引きつき',
            'line_items' => [
                ['description' => '作業', 'quantity' => 1, 'unit_price_cents' => 330000, 'tax_rate_bps' => 1000],
                ['description' => 'お値引き', 'quantity' => 1, 'unit_price_cents' => -100000, 'tax_rate_bps' => 1000],
            ],
        ]);

        self::assertCount(2, $parsed['lines']);
        self::assertSame(-100000, $parsed['lines'][1]->unitPriceCents);
    }

    public function test_rejects_presets_whose_rate_group_is_negative(): void
    {
        $this->expectException(ValidationException::class);
        TemplateField::parse([
            'name' => '値引きだけ',
            'line_items' => [
                ['description' => 'お値引き', 'quantity' => 1, 'unit_price_cents' => -100, 'tax_rate_bps' => 1000],
            ],
        ]);
    }
}
