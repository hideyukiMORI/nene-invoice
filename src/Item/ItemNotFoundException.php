<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use RuntimeException;

final class ItemNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Item {$id} not found.");
    }
}
