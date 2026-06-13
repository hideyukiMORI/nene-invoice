<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice\Pdf;

use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\InvoiceWithLines;
use NeneInvoice\Invoice\Pdf\InvoicePdfData;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Pdf\MpdfFactory;
use NeneInvoice\Pdf\PdfStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every PDF appearance combination (Issue #449) must render a valid PDF. This is
 * the feasibility guard for the bundled IPAex fonts (both ゴシック and 明朝 must
 * load) and the regression guard that no template breaks generation or drops the
 * mode=ja CJK font for body text.
 */
final class InvoicePdfAppearanceTest extends TestCase
{
    #[DataProvider('appearanceProvider')]
    public function test_renders_every_appearance_combination(string $template, string $spacing, string $font): void
    {
        $pdf = $this->generate($template, $spacing, $font);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(2000, strlen($pdf));
        // Body text keeps the mode=ja CJK font regardless of heading choice.
        self::assertStringContainsString('Sun-ExtA', $pdf);
        self::assertStringNotContainsString('DejaVu', $pdf);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function appearanceProvider(): iterable
    {
        foreach (PdfStyle::TEMPLATES as $template) {
            foreach (PdfStyle::SPACINGS as $spacing) {
                foreach (PdfStyle::FONTS as $font) {
                    yield "$template/$spacing/$font" => [$template, $spacing, $font];
                }
            }
        }
    }

    private function generate(string $template, string $spacing, string $font): string
    {
        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 300000,
            taxCents: 24000,
            totalCents: 324000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01',
            dueAt: '2026-05-31',
            isQualifiedInvoice: true,
        );
        $lines = [
            new LineItem(LineItemParent::Invoice, 1, 'Webサイト制作（基本プラン）', 1, 200000, 1000),
            new LineItem(LineItemParent::Invoice, 1, '保守サポート（軽減税率対象）', 1, 100000, 800),
        ];
        $company = new CompanySettings(
            organizationId: 1,
            legalName: '株式会社ネネ商会',
            registrationNumber: 'T1234567890123',
            pdfTemplate: $template,
            pdfSpacing: $spacing,
            pdfHeadingFont: $font,
        );
        $data = new InvoicePdfData(
            new InvoiceWithLines($invoice, $lines, 324000),
            $company,
            new Client(organizationId: 1, name: '株式会社サンプル製作所'),
        );

        return (new InvoicePdfGenerator(new TaxCalculator(), new MpdfFactory()))->generate($data);
    }
}
