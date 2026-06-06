<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

interface GetQuoteByIdUseCaseInterface
{
    public function execute(int $id): QuoteWithLines;
}
