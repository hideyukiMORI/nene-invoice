<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteClientUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * Soft-deletes a client within the caller's organization. Cross-organization
     * targets are reported as not found.
     *
     * @throws ClientNotFoundException
     */
    public function execute(int $organizationId, ?int $actorUserId, int $id): void
    {
        $existing = $this->clients->findById($id);

        if ($existing === null || $existing->organizationId !== $organizationId) {
            throw new ClientNotFoundException($id);
        }

        $this->clients->delete($id);

        $this->audit->record($actorUserId, $organizationId, 'client.deleted', 'client', $id, ClientResponse::toArray($existing), null);
    }
}
