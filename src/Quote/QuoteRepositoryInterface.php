<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Persistence for quotes. Every query is scoped to the organization held in the
 * request-scoped org holder (ADR 0006). Reads exclude soft-deleted rows;
 * `delete` is soft.
 */
interface QuoteRepositoryInterface
{
    public function findById(int $id): ?Quote;

    /** @return list<Quote> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    public function save(Quote $quote): int;

    /** @throws QuoteNotFoundException */
    public function update(Quote $quote): void;

    /** @throws QuoteNotFoundException */
    public function delete(int $id): void;
}
