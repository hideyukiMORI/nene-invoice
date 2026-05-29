<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class GetClientByIdUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * Fetches a client that belongs to the given organization. A client from
     * another organization (or a missing/soft-deleted id) is reported as not
     * found so cross-tenant existence is not leaked.
     *
     * @throws ClientNotFoundException
     */
    public function execute(int $organizationId, int $id): Client
    {
        $client = $this->clients->findById($id);

        if ($client === null || $client->organizationId !== $organizationId) {
            throw new ClientNotFoundException($id);
        }

        return $client;
    }
}
