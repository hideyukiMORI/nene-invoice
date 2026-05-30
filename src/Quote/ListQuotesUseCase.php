<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

final readonly class ListQuotesUseCase
{
    public function __construct(
        private QuoteRepositoryInterface $quotes,
    ) {
    }

    public function execute(int $limit, int $offset): ListQuotesResult
    {
        return new ListQuotesResult(
            $this->quotes->findAll($limit, $offset),
            $this->quotes->count(),
        );
    }
}
