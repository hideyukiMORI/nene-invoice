<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class ListClientsUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    public function execute(int $organizationId, int $limit, int $offset): ListClientsResult
    {
        return new ListClientsResult(
            $this->clients->findAllByOrganization($organizationId, $limit, $offset),
            $this->clients->countByOrganization($organizationId),
        );
    }
}
