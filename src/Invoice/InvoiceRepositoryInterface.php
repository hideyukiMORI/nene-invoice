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

    public function save(Invoice $invoice): int;

    /** @throws InvoiceNotFoundException */
    public function update(Invoice $invoice): void;

    /** @throws InvoiceNotFoundException */
    public function delete(int $id): void;
}
