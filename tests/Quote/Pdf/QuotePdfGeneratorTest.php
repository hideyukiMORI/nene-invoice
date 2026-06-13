<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Quote\Pdf;

use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Pdf\MpdfFactory;
use NeneInvoice\Quote\Pdf\QuotePdfData;
use NeneInvoice\Quote\Pdf\QuotePdfGenerator;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteStatus;
use NeneInvoice\Quote\QuoteWithLines;
use PHPUnit\Framework\TestCase;

final class QuotePdfGeneratorTest extends TestCase
{
    public function test_renders_japanese_with_a_cjk_font_not_dejavu(): void
    {
        $quote = new Quote(
            organizationId: 1,
            clientId: 1,
            quoteNumber: 'EST-2026-001',
            status: QuoteStatus::Sent,
            subtotalCents: 450000,
            taxCents: 45000,
            totalCents: 495000,
            issuedAt: '2026-05-01',
            validUntil: '2026-06-30',
        );
        $lines = [
            new LineItem(LineItemParent::Quote, 1, '保守費用（月額）', 3, 50000, 1000),
        ];
        $data = new QuotePdfData(
            new QuoteWithLines($quote, $lines),
            new CompanySettings(organizationId: 1, legalName: '株式会社ネネ商会'),
            new Client(organizationId: 1, name: '株式会社サンプル製作所'),
        );

        $pdf = (new QuotePdfGenerator(new TaxCalculator(), new MpdfFactory()))->generate($data);

        self::assertStringStartsWith('%PDF', $pdf);
        // Regression guard: Japanese must embed a CJK font (mode=ja), never the
        // non-CJK DejaVu — which would render every 日本語 character as tofu (□).
        self::assertStringContainsString('Sun-ExtA', $pdf);
        self::assertStringNotContainsString('DejaVu', $pdf);
    }
}
