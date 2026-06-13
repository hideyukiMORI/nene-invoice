<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

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

/**
 * Verifies that InvoicePdfGenerator produces a valid PDF binary for a
 * qualified invoice with two tax rate lines (10% / 8%).
 */
final class InvoicePdfTest extends TestCase
{
    private InvoicePdfGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new InvoicePdfGenerator(new TaxCalculator(), new MpdfFactory());
    }

    public function test_generates_pdf_for_qualified_invoice(): void
    {
        $data   = $this->buildData(isQualified: true);
        $bytes  = $this->generator->generate($data);

        self::assertStringStartsWith('%PDF-', $bytes, 'Output must be a PDF binary.');
        self::assertGreaterThan(1000, strlen($bytes), 'PDF must have meaningful content.');
    }

    public function test_generates_pdf_for_non_qualified_invoice(): void
    {
        $data  = $this->buildData(isQualified: false);
        $bytes = $this->generator->generate($data);

        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_generates_pdf_with_minimal_company_info(): void
    {
        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Draft,
            subtotalCents: 10000,
            taxCents: 1000,
            totalCents: 11000,
            isQualifiedInvoice: false,
            id: 1,
        );
        $lines = [
            new LineItem(LineItemParent::Invoice, 1, 'テスト', 1, 10000, 1000, 0, 1),
        ];
        $company = new CompanySettings(organizationId: 1, legalName: 'テスト株式会社');
        $client  = new Client(organizationId: 1, name: '取引先A');

        $data  = new InvoicePdfData(new InvoiceWithLines($invoice, $lines), $company, $client);
        $bytes = $this->generator->generate($data);

        self::assertStringStartsWith('%PDF-', $bytes);
    }

    private function buildData(bool $isQualified): InvoicePdfData
    {
        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 106000,
            taxCents: 10480,
            totalCents: 116480,
            isQualifiedInvoice: $isQualified,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-30 00:00:00',
            dueAt: '2026-06-30 00:00:00',
            notes: '振込手数料はご負担ください。',
            id: 1,
        );

        $lines = [
            new LineItem(LineItemParent::Invoice, 1, 'コンサルティング料', 1, 100000, 1000, 0, 1),
            new LineItem(LineItemParent::Invoice, 1, '書籍（8%）', 3, 2000, 800, 1, 2),
        ];

        $company = new CompanySettings(
            organizationId: 1,
            legalName: 'ネーネー株式会社',
            address: '〒150-0001 東京都渋谷区神宮前1-1-1',
            registrationNumber: 'T1234567890123',
            bankName: 'ネーネー銀行',
            bankBranch: '渋谷',
            accountType: '普通',
            accountNumber: '1234567',
        );

        $client = new Client(
            organizationId: 1,
            name: '株式会社サンプル',
            billingAddress: '〒100-0001 東京都千代田区丸の内1-1-1',
        );

        return new InvoicePdfData(new InvoiceWithLines($invoice, $lines), $company, $client);
    }
}
