<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

interface ListQuotesUseCaseInterface
{
    public function executeAdmin(QuoteListFilter $filter, QuoteSort $sort, int $limit, int $offset): ListQuotesResult;
}
