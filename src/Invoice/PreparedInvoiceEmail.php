<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Client\Client;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\LineItem\LineItem;

/**
 * Internal value object holding everything needed to either send an invoice
 * email or render its preview (#626). Built once by
 * {@see SendInvoiceEmailUseCase::prepare()} after the sendability / client-email
 * guards pass, so `execute()` and `preview()` share the exact same subject /
 * body / recipient. Not exposed over HTTP — the preview response uses
 * {@see SendInvoiceEmailPreview}.
 */
final readonly class PreparedInvoiceEmail
{
    /**
     * @param list<LineItem> $lines
     */
    public function __construct(
        public Invoice $invoice,
        public array $lines,
        public Client $client,
        public CompanySettings $company,
        public string $invoiceNumber,
        public string $subject,
        public string $bodyHtml,
    ) {
    }
}
