<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface DeleteClientUseCaseInterface
{
    public function execute(?int $actorUserId, int $id): void;
}
