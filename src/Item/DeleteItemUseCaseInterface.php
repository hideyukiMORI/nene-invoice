<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface DeleteItemUseCaseInterface
{
    public function execute(?int $actorUserId, int $id): void;
}
