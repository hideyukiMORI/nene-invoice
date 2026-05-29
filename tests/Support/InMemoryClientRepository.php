<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\ClientRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Soft-deleted clients are
 * excluded from reads, matching the PDO implementation.
 */
final class InMemoryClientRepository implements ClientRepositoryInterface
{
    /** @var array<int, Client> */
    private array $byId = [];
    private int $nextId = 1;

    public function findById(int $id): ?Client
    {
        $client = $this->byId[$id] ?? null;

        return $client !== null && !$client->isDeleted ? $client : null;
    }

    /** @return list<Client> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (Client $c): bool => $c->organizationId === $organizationId && !$c->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (Client $c): bool => $c->organizationId === $organizationId && !$c->isDeleted,
        ));
    }

    public function save(Client $client): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Client(
            organizationId: $client->organizationId,
            name: $client->name,
            contactName: $client->contactName,
            email: $client->email,
            billingAddress: $client->billingAddress,
            registrationNumber: $client->registrationNumber,
            isDeleted: false,
            id: $id,
            createdAt: '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );

        return $id;
    }

    public function update(Client $client): void
    {
        if ($client->id === null || $this->findById($client->id) === null) {
            throw new ClientNotFoundException($client->id ?? 0);
        }

        $this->byId[$client->id] = $client;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new ClientNotFoundException($id);
        }

        $this->byId[$id] = new Client(
            organizationId: $existing->organizationId,
            name: $existing->name,
            contactName: $existing->contactName,
            email: $existing->email,
            billingAddress: $existing->billingAddress,
            registrationNumber: $existing->registrationNumber,
            isDeleted: true,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );
    }
}
