<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use LogicException;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateClientUseCase
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * Updates a client within the caller's organization. A client from another
     * organization (or soft-deleted) is reported as not found.
     *
     * @throws ClientNotFoundException
     * @throws InvalidRegistrationNumberException
     */
    public function execute(int $organizationId, int $id, UpdateClientInput $input): Client
    {
        $existing = $this->clients->findById($id);

        if ($existing === null || $existing->organizationId !== $organizationId) {
            throw new ClientNotFoundException($id);
        }

        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $this->clients->update(new Client(
            organizationId: $existing->organizationId,
            name: $input->name,
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

        return $updated;
    }
}
