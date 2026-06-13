<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice\Pdf;

use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Invoice\InvoiceWithLines;

/** Aggregated data required to render one invoice PDF. */
final readonly class InvoicePdfData
{
    public function __construct(
        public InvoiceWithLines $invoiceWithLines,
        public CompanySettings $companySettings,
        public Client $client,
        /** Issuer seal (社印) as a base64 PNG, or null when none is set (Issue #448). */
        public ?string $sealImageBase64 = null,
    ) {
    }
}
