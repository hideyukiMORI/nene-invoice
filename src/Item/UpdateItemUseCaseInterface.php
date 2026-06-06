<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface UpdateItemUseCaseInterface
{
    public function execute(?int $actorUserId, int $id, UpdateItemInput $input): Item;
}
