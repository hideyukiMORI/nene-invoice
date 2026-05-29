<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Persistence for clients. All reads exclude soft-deleted rows; `delete` is a
 * soft delete (sets `is_deleted`).
 */
interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    /** @return list<Client> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;

    public function save(Client $client): int;

    /** @throws ClientNotFoundException */
    public function update(Client $client): void;

    /** @throws ClientNotFoundException */
    public function delete(int $id): void;
}
