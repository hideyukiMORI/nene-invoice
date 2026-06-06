<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteClientUseCase implements DeleteClientUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClientRepositoryInterface $clientsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ClientRepositoryInterface $clients,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $clientsFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Soft-deletes a client in the resolved organization. The repository scopes
     * the lookup/delete to the request org, so cross-organization targets
     * surface as not found. The delete and its audit record commit atomically
     * (Issue #352).
     *
     * @throws ClientNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->clients->findById($id);

        if ($existing === null) {
            throw new ClientNotFoundException($id);
        }

        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $existing): null {
            ($this->clientsFactory)($exec)->delete($id);

            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'client.deleted', 'client', $id, ClientResponse::toArray($existing), null);

            return null;
        });
    }
}
