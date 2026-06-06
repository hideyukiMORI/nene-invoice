<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

final readonly class ListItemsResult
{
    /** @param list<Item> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
