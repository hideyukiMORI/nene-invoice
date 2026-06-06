<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface UpdateClientUseCaseInterface
{
    public function execute(?int $actorUserId, int $id, UpdateClientInput $input): Client;
}
