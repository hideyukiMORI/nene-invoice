<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Persistence for invoices. Reads exclude soft-deleted rows; `delete` is soft.
 */
interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    /** @return list<Invoice> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;

    /** @return list<Invoice> */
    public function findByOrganizationFiltered(
        int $organizationId,
        InvoiceListFilter $filter,
        int $limit,
        int $offset,
    ): array;

    public function countByOrganizationFiltered(int $organizationId, InvoiceListFilter $filter): int;

    /**
     * Returns counts and recent unpaid invoices for the dashboard in a single query.
     * `$now` is a comparable datetime string (e.g. `Y-m-d H:i:s`); defaults to now().
     *
     * @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>}
     */
    public function getDashboardData(int $organizationId, string $now): array;

    public function save(Invoice $invoice): int;

    /** @throws InvoiceNotFoundException */
    public function update(Invoice $invoice): void;

    /** @throws InvoiceNotFoundException */
    public function delete(int $id): void;
}
