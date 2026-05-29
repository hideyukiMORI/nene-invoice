<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Persistence for quotes. Reads exclude soft-deleted rows; `delete` is soft.
 */
interface QuoteRepositoryInterface
{
    public function findById(int $id): ?Quote;

    /** @return list<Quote> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;

    public function save(Quote $quote): int;

    /** @throws QuoteNotFoundException */
    public function update(Quote $quote): void;

    /** @throws QuoteNotFoundException */
    public function delete(int $id): void;
}
