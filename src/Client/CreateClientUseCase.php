<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class CreateClientUseCase implements CreateClientUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClientRepositoryInterface $clientsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $clientsFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Creates a client in the resolved organization (the repository forces the
     * org from the request-scoped holder, never from request input). The write
     * and its audit record commit atomically (Issue #352).
     *
     * @throws InvalidRegistrationNumberException
     */
    public function execute(?int $actorUserId, CreateClientInput $input): Client
    {
        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $organizationId = $this->orgId->get();

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $input): Client {
            $clients = ($this->clientsFactory)($exec);

            $id = $clients->save(new Client(
                organizationId: $organizationId,
                name: $input->name,
                nameKana: $input->nameKana,
                contactName: $input->contactName,
                email: $input->email,
                billingAddress: $input->billingAddress,
                registrationNumber: $input->registrationNumber,
            ));

            $created = $clients->findById($id);

            if ($created === null) {
                throw new LogicException('Client disappeared immediately after creation.');
            }

            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'client.created', 'client', $id, null, ClientResponse::toArray($created));

            return $created;
        });
    }
}
