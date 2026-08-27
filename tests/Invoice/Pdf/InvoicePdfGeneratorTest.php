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
use PHPUnit\Framework\TestCase;

final class InvoicePdfGeneratorTest extends TestCase
{
    public function test_renders_japanese_with_the_bundled_ipaex_font(): void
    {
        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 300000,
            taxCents: 30000,
            totalCents: 330000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-01',
            dueAt: '2026-05-31',
        );
        $lines = [
            new LineItem(LineItemParent::Invoice, 1, 'Webサイト制作（基本プラン）', 1, 300000, 1000),
        ];
        $data = new InvoicePdfData(
            new InvoiceWithLines($invoice, $lines, 330000),
            new CompanySettings(organizationId: 1, legalName: '株式会社ネネ商会'),
            new Client(organizationId: 1, name: '株式会社サンプル製作所'),
        );

        $pdf = (new InvoicePdfGenerator(new TaxCalculator(), new MpdfFactory()))->generate($data);

        self::assertStringStartsWith('%PDF', $pdf);
        // Regression guards: Japanese must embed the bundled IPAex (Japanese) font —
        // never DejaVu (tofu □) and never mPDF's Sun-ExtA, a Chinese font whose
        // glyph shapes are wrong for Japanese (2026-08-27, first real quote).
        self::assertStringContainsString('IPAexGothic', $pdf);
        self::assertStringNotContainsString('Sun-ExtA', $pdf);
        self::assertStringNotContainsString('DejaVu', $pdf);
    }

    public function test_renders_a_valid_pdf_with_a_base64_logo(): void
    {
        // 1x1 transparent PNG stored in company_settings.logo_url (Issue #510).
        $logo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 300000,
            taxCents: 30000,
            totalCents: 330000,
            invoiceNumber: 'INV-2026-002',
            issuedAt: '2026-05-01',
            dueAt: '2026-05-31',
        );
        $lines = [
            new LineItem(LineItemParent::Invoice, 1, 'コンサルティング', 1, 300000, 1000),
        ];
        $data = new InvoicePdfData(
            new InvoiceWithLines($invoice, $lines, 330000),
            new CompanySettings(organizationId: 1, legalName: '株式会社ネネ商会', logoUrl: $logo),
            new Client(organizationId: 1, name: '株式会社サンプル製作所'),
        );

        $pdf = (new InvoicePdfGenerator(new TaxCalculator(), new MpdfFactory()))->generate($data);

        self::assertStringStartsWith('%PDF', $pdf);
    }
}
