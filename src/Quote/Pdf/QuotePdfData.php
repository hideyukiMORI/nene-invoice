<?php

declare(strict_types=1);

namespace NeneInvoice\Quote\Pdf;

use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Quote\QuoteWithLines;

/** Aggregated data required to render one quote PDF. */
final readonly class QuotePdfData
{
    public function __construct(
        public QuoteWithLines $quoteWithLines,
        public CompanySettings $companySettings,
        public Client $client,
        /** Issuer seal (社印) as a base64 PNG, or null when none is set (Issue #448). */
        public ?string $sealImageBase64 = null,
    ) {
    }
}
