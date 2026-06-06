<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateClientUseCase implements UpdateClientUseCaseInterface
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
     * Updates a client in the resolved organization. The repository scopes the
     * read/write to the request org, so a client from another organization (or
     * soft-deleted) surfaces as not found.
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

        $this->clients->update(new Client(
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

        $updated = $this->clients->findById($id);

        if ($updated === null) {
            throw new LogicException('Client disappeared immediately after update.');
        }

        $this->audit->record($actorUserId, $this->orgId->get(), 'client.updated', 'client', $id, ClientResponse::toArray($existing), ClientResponse::toArray($updated));

        return $updated;
    }
}
