<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Persistence for invoices. Every query is scoped to the organization held in
 * the request-scoped org holder (ADR 0006); callers never pass an organization
 * id. Reads exclude soft-deleted rows; `delete` is soft.
 */
interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    /** @return list<Invoice> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /** @return list<Invoice> */
    public function findFiltered(InvoiceListFilter $filter, int $limit, int $offset): array;

    public function countFiltered(InvoiceListFilter $filter): int;

    /**
     * Returns counts and recent unpaid invoices for the dashboard in a single query.
     * `$now` is a comparable datetime string (e.g. `Y-m-d H:i:s`).
     *
     * @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>}
     */
    public function getDashboardData(string $now): array;

    public function save(Invoice $invoice): int;

    /** @throws InvoiceNotFoundException */
    public function update(Invoice $invoice): void;

    /** @throws InvoiceNotFoundException */
    public function delete(int $id): void;
}
