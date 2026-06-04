<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class ListClientsUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    public function execute(int $limit, int $offset): ListClientsResult
    {
        return new ListClientsResult(
            $this->clients->findAll($limit, $offset),
            $this->clients->count(),
        );
    }

    /** Admin list: search + sort. */
    public function executeAdmin(
        ClientListFilter $filter,
        ClientSort $sort,
        int $limit,
        int $offset,
    ): ListClientsResult {
        return new ListClientsResult(
            $this->clients->findForAdminList($filter, $sort, $limit, $offset),
            $this->clients->countForAdminList($filter),
        );
    }
}
