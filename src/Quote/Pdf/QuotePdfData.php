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
    ) {
    }
}
