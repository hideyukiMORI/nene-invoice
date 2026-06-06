<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

interface CreateItemUseCaseInterface
{
    public function execute(?int $actorUserId, CreateItemInput $input): Item;
}
