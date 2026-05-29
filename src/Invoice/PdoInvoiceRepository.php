<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoInvoiceRepository implements InvoiceRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, client_id, quote_id, invoice_number, status, is_qualified_invoice, issued_at, due_at, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?Invoice
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM invoices WHERE id = ? AND is_deleted = 0',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Invoice> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM invoices WHERE organization_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT ? OFFSET ?',
            [$organizationId, $limit, $offset],
        );

        return array_map(fn (array $row): Invoice => $this->mapRow($row), $rows);
    }

    public function countByOrganization(int $organizationId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM invoices WHERE organization_id = ? AND is_deleted = 0',
            [$organizationId],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Invoice $invoice): int
    {
        $now = date('Y-m-d H:i:s');

        $this->query->execute(
            'INSERT INTO invoices (organization_id, client_id, quote_id, invoice_number, status, is_qualified_invoice, issued_at, due_at, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $invoice->organizationId,
                $invoice->clientId,
                $invoice->quoteId,
                $invoice->invoiceNumber,
                $invoice->status->value,
                $invoice->isQualifiedInvoice ? 1 : 0,
                $invoice->issuedAt,
                $invoice->dueAt,
                $invoice->subtotalCents,
                $invoice->taxCents,
                $invoice->totalCents,
                $invoice->notes,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function update(Invoice $invoice): void
    {
        if ($invoice->id === null) {
            throw new InvoiceNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE invoices SET client_id = ?, quote_id = ?, invoice_number = ?, status = ?, is_qualified_invoice = ?, issued_at = ?, due_at = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ?, notes = ?, updated_at = ? WHERE id = ? AND is_deleted = 0',
            [
                $invoice->clientId,
                $invoice->quoteId,
                $invoice->invoiceNumber,
                $invoice->status->value,
                $invoice->isQualifiedInvoice ? 1 : 0,
                $invoice->issuedAt,
                $invoice->dueAt,
                $invoice->subtotalCents,
                $invoice->taxCents,
                $invoice->totalCents,
                $invoice->notes,
                $now,
                $invoice->id,
            ],
        );

        if ($affected === 0 && $this->findById($invoice->id) === null) {
            throw new InvoiceNotFoundException($invoice->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new InvoiceNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE invoices SET is_deleted = 1, deleted_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Invoice
    {
        return new Invoice(
            organizationId: (int) $row['organization_id'],
            clientId: (int) $row['client_id'],
            status: InvoiceStatus::from((string) $row['status']),
            subtotalCents: (int) $row['subtotal_cents'],
            taxCents: (int) $row['tax_cents'],
            totalCents: (int) $row['total_cents'],
            isQualifiedInvoice: (bool) $row['is_qualified_invoice'],
            quoteId: isset($row['quote_id']) ? (int) $row['quote_id'] : null,
            invoiceNumber: isset($row['invoice_number']) && $row['invoice_number'] !== '' ? (string) $row['invoice_number'] : null,
            issuedAt: isset($row['issued_at']) && $row['issued_at'] !== '' ? (string) $row['issued_at'] : null,
            dueAt: isset($row['due_at']) && $row['due_at'] !== '' ? (string) $row['due_at'] : null,
            notes: isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
