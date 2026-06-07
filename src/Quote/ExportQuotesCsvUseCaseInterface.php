<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

interface ExportQuotesCsvUseCaseInterface
{
    public function execute(QuoteListFilter $filter): string;
}
