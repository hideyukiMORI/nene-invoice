<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface CreateClientUseCaseInterface
{
    public function execute(?int $actorUserId, CreateClientInput $input): Client;
}
