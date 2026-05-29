<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class DeleteClientUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * Soft-deletes a client within the caller's organization. Cross-organization
     * targets are reported as not found.
     *
     * @throws ClientNotFoundException
     */
    public function execute(int $organizationId, int $id): void
    {
        $existing = $this->clients->findById($id);

        if ($existing === null || $existing->organizationId !== $organizationId) {
            throw new ClientNotFoundException($id);
        }

        $this->clients->delete($id);
    }
}
