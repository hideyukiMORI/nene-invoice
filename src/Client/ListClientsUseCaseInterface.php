<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

interface ListClientsUseCaseInterface
{
    public function executeAdmin(ClientListFilter $filter, ClientSort $sort, int $limit, int $offset): ListClientsResult;
}
