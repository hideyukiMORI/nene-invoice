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

    /**
     * Admin list: filtered + searched + sorted, joined with the client name.
     *
     * @return list<QuoteListRow>
     */
    public function findForAdminList(QuoteListFilter $filter, QuoteSort $sort, int $limit, int $offset): array;

    public function countForAdminList(QuoteListFilter $filter): int;

    /**
     * Returns the non-deleted quotes matching the given admin filter, joined
     * with the client name. Intended for CSV export only: it applies the same
     * predicates as {@see findForAdminList()} (so the export mirrors the list)
     * but never paginates.
     *
     * @return list<array{quote_number: string, issued_at: string|null, valid_until: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string}>
     */
    public function findForExport(QuoteListFilter $filter): array;

    public function save(Quote $quote): int;

    /** @throws QuoteNotFoundException */
    public function update(Quote $quote): void;

    /** @throws QuoteNotFoundException */
    public function delete(int $id): void;
}
