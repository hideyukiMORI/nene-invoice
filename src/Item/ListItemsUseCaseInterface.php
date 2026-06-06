<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface ListItemsUseCaseInterface
{
    public function executeAdmin(ItemListFilter $filter, ItemSort $sort, int $limit, int $offset): ListItemsResult;
}
