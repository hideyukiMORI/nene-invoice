<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoInvoiceRepository implements InvoiceRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, client_id, quote_id, invoice_number, status, is_qualified_invoice, issued_at, due_at, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function findById(int $id): ?Invoice
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM invoices WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function existsForQuote(int $quoteId): bool
    {
        $row = $this->query->fetchOne(
            'SELECT 1 AS hit FROM invoices WHERE quote_id = ? AND organization_id = ? AND is_deleted = 0 LIMIT 1',
            [$quoteId, $this->orgId->get()],
        );

        return $row !== null;
    }

    /** @return list<Invoice> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM invoices WHERE organization_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): Invoice => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM invoices WHERE organization_id = ? AND is_deleted = 0',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /** @return list<Invoice> */
    public function findFiltered(InvoiceListFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM invoices WHERE ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map(fn (array $row): Invoice => $this->mapRow($row), $rows);
    }

    public function countFiltered(InvoiceListFilter $filter): int
    {
        [$where, $params] = $this->buildWhere($filter);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM invoices WHERE ' . $where, $params);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildWhere(InvoiceListFilter $filter): array
    {
        $clauses = ['organization_id = ?', 'is_deleted = 0'];
        /** @var list<int|string> $params */
        $params = [$this->orgId->get()];

        if ($filter->statuses !== []) {
            $placeholders = implode(', ', array_fill(0, count($filter->statuses), '?'));
            $clauses[] = 'status IN (' . $placeholders . ')';
            foreach ($filter->statuses as $status) {
                $params[] = $status;
            }
        }

        if ($filter->clientId !== null) {
            $clauses[] = 'client_id = ?';
            $params[] = $filter->clientId;
        }

        if ($filter->dueBefore !== null) {
            $clauses[] = 'due_at IS NOT NULL AND due_at < ?';
            $params[] = $filter->dueBefore;
        }

        if ($filter->dueAfter !== null) {
            $clauses[] = 'due_at IS NOT NULL AND due_at > ?';
            $params[] = $filter->dueAfter;
        }

        // Outstanding > 0 ⟺ the invoice is issued or partially paid (our model).
        if ($filter->outstandingOnly || $filter->overdueOnly) {
            $clauses[] = "status IN ('issued', 'partially_paid')";
        }

        if ($filter->overdueOnly) {
            $clauses[] = 'due_at IS NOT NULL AND due_at < ?';
            $params[] = $filter->todayOrNow();
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>}
     */
    public function getDashboardData(string $now): array
    {
        $orgId = $this->orgId->get();

        $countsRow = $this->query->fetchOne(
            'SELECT
                SUM(CASE WHEN status IN (\'issued\', \'partially_paid\') THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN status IN (\'issued\', \'partially_paid\') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) AS overdue_count
            FROM invoices
            WHERE organization_id = ? AND is_deleted = 0',
            [$now, $orgId],
        );

        $unpaidCount  = $countsRow !== null ? (int) ($countsRow['unpaid_count'] ?? 0) : 0;
        $overdueCount = $countsRow !== null ? (int) ($countsRow['overdue_count'] ?? 0) : 0;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . '
            FROM invoices
            WHERE organization_id = ? AND is_deleted = 0 AND status IN (\'issued\', \'partially_paid\')
            ORDER BY id DESC
            LIMIT 5',
            [$orgId],
        );

        return [
            'unpaid_count'  => $unpaidCount,
            'overdue_count' => $overdueCount,
            'recent_unpaid' => array_map($this->mapRow(...), $rows),
        ];
    }

    public function save(Invoice $invoice): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO invoices (organization_id, client_id, quote_id, invoice_number, status, is_qualified_invoice, issued_at, due_at, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $this->orgId->get(),
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
            'UPDATE invoices SET client_id = ?, quote_id = ?, invoice_number = ?, status = ?, is_qualified_invoice = ?, issued_at = ?, due_at = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ?, notes = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = 0',
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
                $this->orgId->get(),
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
            'UPDATE invoices SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
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
