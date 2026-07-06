<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class DeleteClientUseCase implements DeleteClientUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClientRepositoryInterface $clientsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ClientRepositoryInterface $clients,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $clientsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
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

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'client.deleted',
                entityType: 'client',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ClientResponse::toArray($existing),
                after: null,
            ));

            return null;
        });
    }
}
