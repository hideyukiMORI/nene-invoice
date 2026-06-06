<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

final readonly class ListQuotesUseCase implements ListQuotesUseCaseInterface
{
    public function __construct(
        private QuoteRepositoryInterface $quotes,
    ) {
    }

    /**
     * Admin list: search / filter / sort, with client names resolved by the
     * query's join (so the UI shows names, not ids).
     */
    public function executeAdmin(
        QuoteListFilter $filter,
        QuoteSort $sort,
        int $limit,
        int $offset,
    ): ListQuotesResult {
        $rows = $this->quotes->findForAdminList($filter, $sort, $limit, $offset);

        $items       = [];
        $clientNames = [];
        foreach ($rows as $row) {
            $items[] = $row->quote;
            if ($row->quote->id !== null) {
                $clientNames[$row->quote->id] = $row->clientName;
            }
        }

        return new ListQuotesResult($items, $this->quotes->countForAdminList($filter), $clientNames);
    }
}
