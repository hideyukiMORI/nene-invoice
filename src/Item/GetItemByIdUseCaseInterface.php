<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface GetItemByIdUseCaseInterface
{
    public function execute(int $id): Item;
}
