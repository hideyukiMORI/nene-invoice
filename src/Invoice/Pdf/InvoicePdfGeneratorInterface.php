<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice\Pdf;

interface InvoicePdfGeneratorInterface
{
    /** @throws \RuntimeException */
    public function generate(InvoicePdfData $data): string;
}
