<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

final readonly class ListItemsUseCase
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {
    }

    public function execute(int $limit, int $offset): ListItemsResult
    {
        return new ListItemsResult(
            $this->items->findAll($limit, $offset),
            $this->items->count(),
        );
    }

    /** Admin list: search + sort. */
    public function executeAdmin(
        ItemListFilter $filter,
        ItemSort $sort,
        int $limit,
        int $offset,
    ): ListItemsResult {
        return new ListItemsResult(
            $this->items->findForAdminList($filter, $sort, $limit, $offset),
            $this->items->countForAdminList($filter),
        );
    }
}
