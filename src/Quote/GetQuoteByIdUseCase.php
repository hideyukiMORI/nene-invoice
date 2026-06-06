<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class GetQuoteByIdUseCase implements GetQuoteByIdUseCaseInterface
{
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private LineItemRepositoryInterface $lineItems,
    ) {
    }

    /**
     * Fetches a quote (with its line items) belonging to the organization. A
     * quote from another organization (or missing/soft-deleted) is not found.
     *
     * @throws QuoteNotFoundException
     */
    public function execute(int $id): QuoteWithLines
    {
        $quote = $this->quotes->findById($id);

        if ($quote === null) {
            throw new QuoteNotFoundException($id);
        }

        return new QuoteWithLines($quote, $this->lineItems->findByParent(LineItemParent::Quote, $id));
    }
}
