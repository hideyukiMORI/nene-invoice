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

final class InvoiceSealPdfTest extends TestCase
{
    public function test_seal_is_embedded_as_an_image_when_present(): void
    {
        $withoutSeal = $this->render(null);
        $withSeal    = $this->render(self::pngBase64());

        self::assertStringStartsWith('%PDF', $withSeal);
        self::assertStringStartsWith('%PDF', $withoutSeal);
        // The embedded seal PNG materially grows the document; an absent seal
        // renders no image bytes at all. mPDF compresses streams, so size — not a
        // plaintext marker — is the reliable signal that the image was embedded.
        self::assertGreaterThan(strlen($withoutSeal) + 500, strlen($withSeal));
    }

    private function render(?string $sealBase64): string
    {
        $invoice = new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 100000,
            taxCents: 10000,
            totalCents: 110000,
            invoiceNumber: 'INV-2026-009',
            issuedAt: '2026-05-01',
            dueAt: '2026-05-31',
            isQualifiedInvoice: true,
        );
        $lines = [new LineItem(LineItemParent::Invoice, 1, 'コンサルティング', 1, 100000, 1000)];
        $data  = new InvoicePdfData(
            new InvoiceWithLines($invoice, $lines, 110000),
            new CompanySettings(organizationId: 1, legalName: '株式会社ネネ商会', registrationNumber: 'T1234567890123'),
            new Client(organizationId: 1, name: '株式会社サンプル製作所'),
            $sealBase64,
        );

        return (new InvoicePdfGenerator(new TaxCalculator(), new MpdfFactory()))->generate($data);
    }

    private static function pngBase64(): string
    {
        $img = imagecreatetruecolor(120, 120);
        $red = imagecolorallocate($img, 200, 0, 0) ?: 0;
        imagefilledellipse($img, 60, 60, 100, 100, $red);
        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        return base64_encode($binary);
    }
}
