<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class GetClientByIdUseCase implements GetClientByIdUseCaseInterface
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * Fetches a client in the current organization. The repository scopes the
     * read to the request-scoped org, so a client from another organization (or
     * a missing/soft-deleted id) surfaces as not found — no cross-tenant leak.
     *
     * @throws ClientNotFoundException
     */
    public function execute(int $id): Client
    {
        $client = $this->clients->findById($id);

        if ($client === null) {
            throw new ClientNotFoundException($id);
        }

        return $client;
    }
}
