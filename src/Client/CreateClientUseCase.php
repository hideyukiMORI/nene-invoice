<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class CreateClientUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * Creates a client in the caller's organization. The organization is taken
     * from the authenticated caller, never from request input.
     *
     * @throws InvalidRegistrationNumberException
     */
    public function execute(int $organizationId, ?int $actorUserId, CreateClientInput $input): Client
    {
        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $id = $this->clients->save(new Client(
            organizationId: $organizationId,
            name: $input->name,
            contactName: $input->contactName,
            email: $input->email,
            billingAddress: $input->billingAddress,
            registrationNumber: $input->registrationNumber,
        ));

        $created = $this->clients->findById($id);

        if ($created === null) {
            throw new LogicException('Client disappeared immediately after creation.');
        }

        $this->audit->record($actorUserId, $organizationId, 'client.created', 'client', $id, null, ClientResponse::toArray($created));

        return $created;
    }
}
