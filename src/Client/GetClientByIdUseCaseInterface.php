<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface GetClientByIdUseCaseInterface
{
    public function execute(int $id): Client;
}
