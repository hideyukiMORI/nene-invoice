<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Persistence for clients. Every query is scoped to the organization held in the
 * request-scoped org holder (ADR 0006) — callers never pass an organization id,
 * so cross-tenant access is impossible to express. Reads exclude soft-deleted
 * rows; `delete` is a soft delete (sets `is_deleted`).
 */
interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    /** @return list<Client> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /**
     * Admin list: searched + sorted.
     *
     * @return list<Client>
     */
    public function findForAdminList(ClientListFilter $filter, ClientSort $sort, int $limit, int $offset): array;

    public function countForAdminList(ClientListFilter $filter): int;

    public function save(Client $client): int;

    /** @throws ClientNotFoundException */
    public function update(Client $client): void;

    /** @throws ClientNotFoundException */
    public function delete(int $id): void;
}
