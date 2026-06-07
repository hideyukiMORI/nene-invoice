<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface ExportItemsCsvUseCaseInterface
{
    public function execute(ItemListFilter $filter): string;
}
