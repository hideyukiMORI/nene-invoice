<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteClientUseCase implements DeleteClientUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ClientRepositoryInterface $clients,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Soft-deletes a client in the resolved organization. The repository scopes
     * the lookup/delete to the request org, so cross-organization targets
     * surface as not found.
     *
     * @throws ClientNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->clients->findById($id);

        if ($existing === null) {
            throw new ClientNotFoundException($id);
        }

        $this->clients->delete($id);

        $this->audit->record($actorUserId, $this->orgId->get(), 'client.deleted', 'client', $id, ClientResponse::toArray($existing), null);
    }
}
