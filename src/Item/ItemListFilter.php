<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Admin read filter for the item list. `search` matches the description
 * (substring). Empty = list everything.
 */
final readonly class ItemListFilter
{
    public function __construct(
        public ?string $search = null,
    ) {
    }
}
