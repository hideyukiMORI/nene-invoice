<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateClientUseCase implements UpdateClientUseCaseInterface
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
     * Updates a client in the resolved organization. The repository scopes the
     * read/write to the request org, so a client from another organization (or
     * soft-deleted) surfaces as not found. The write and its audit record commit
     * atomically (Issue #352).
     *
     * @throws ClientNotFoundException
     * @throws InvalidRegistrationNumberException
     */
    public function execute(?int $actorUserId, int $id, UpdateClientInput $input): Client
    {
        $existing = $this->clients->findById($id);

        if ($existing === null) {
            throw new ClientNotFoundException($id);
        }

        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $organizationId = $this->orgId->get();

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $input, $existing): Client {
            $clients = ($this->clientsFactory)($exec);

            $clients->update(new Client(
                organizationId: $existing->organizationId,
                name: $input->name,
                nameKana: $input->nameKana,
                contactName: $input->contactName,
                email: $input->email,
                billingAddress: $input->billingAddress,
                registrationNumber: $input->registrationNumber,
                isDeleted: false,
                id: $existing->id,
                createdAt: $existing->createdAt,
                updatedAt: $existing->updatedAt,
            ));

            $updated = $clients->findById($id);

            if ($updated === null) {
                throw new LogicException('Client disappeared immediately after update.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'client.updated',
                entityType: 'client',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ClientResponse::toArray($existing),
                after: ClientResponse::toArray($updated),
            ));

            return $updated;
        });
    }
}
