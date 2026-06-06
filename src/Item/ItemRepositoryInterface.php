<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Persistence for the item master. Every query is scoped to the organization
 * held in the request-scoped org holder (ADR 0006) — callers never pass an
 * organization id, so cross-tenant access is impossible to express. Reads
 * exclude soft-deleted rows; `delete` is a soft delete (sets `is_deleted`).
 */
interface ItemRepositoryInterface
{
    public function findById(int $id): ?Item;

    /** @return list<Item> */
    public function findAll(int $limit, int $offset): array;

    /**
     * Admin list: searched + sorted.
     *
     * @return list<Item>
     */
    public function findForAdminList(ItemListFilter $filter, ItemSort $sort, int $limit, int $offset): array;

    public function countForAdminList(ItemListFilter $filter): int;

    public function save(Item $item): int;

    /** @throws ItemNotFoundException */
    public function update(Item $item): void;

    /** @throws ItemNotFoundException */
    public function delete(int $id): void;
}
