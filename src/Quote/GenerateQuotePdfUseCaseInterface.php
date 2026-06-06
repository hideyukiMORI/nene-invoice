<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\Quote\Pdf\QuotePdfData;

interface GenerateQuotePdfUseCaseInterface
{
    public function execute(int $quoteId): QuotePdfData;
}
