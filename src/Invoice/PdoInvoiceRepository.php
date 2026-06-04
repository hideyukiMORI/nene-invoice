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
     * Admin list: filtered + searched + sorted, joined with the client name.
     *
     * @return list<InvoiceListRow>
     */
    public function findForAdminList(InvoiceListFilter $filter, InvoiceSort $sort, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT i.id, i.organization_id, i.client_id, i.quote_id, i.invoice_number, i.status,
                    i.is_qualified_invoice, i.issued_at, i.due_at, i.subtotal_cents, i.tax_cents,
                    i.total_cents, i.notes, i.is_deleted, i.created_at, i.updated_at,
                    COALESCE(c.name, \'\') AS client_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id AND c.is_deleted = 0
             WHERE ' . $where . '
             ORDER BY ' . self::orderByClause($sort) . '
             LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map(
            fn (array $row): InvoiceListRow => new InvoiceListRow(
                $this->mapRow($row),
                (string) ($row['client_name'] ?? ''),
            ),
            $rows,
        );
    }

    public function countForAdminList(InvoiceListFilter $filter): int
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id AND c.is_deleted = 0
             WHERE ' . $where,
            $params,
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * Admin WHERE: columns qualified with the `i` (invoices) / `c` (clients)
     * aliases because the admin query joins clients (search / sort by name).
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildAdminWhere(InvoiceListFilter $filter): array
    {
        $clauses = ['i.organization_id = ?', 'i.is_deleted = 0'];
        /** @var list<int|string> $params */
        $params = [$this->orgId->get()];

        if ($filter->statuses !== []) {
            $placeholders = implode(', ', array_fill(0, count($filter->statuses), '?'));
            $clauses[] = 'i.status IN (' . $placeholders . ')';
            foreach ($filter->statuses as $status) {
                $params[] = $status;
            }
        }

        if ($filter->search !== null) {
            $clauses[] = "(i.invoice_number LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!')";
            $like = '%' . self::escapeLike($filter->search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($filter->dueFrom !== null) {
            $clauses[] = 'i.due_at IS NOT NULL AND i.due_at >= ?';
            $params[] = $filter->dueFrom;
        }

        if ($filter->dueTo !== null) {
            $clauses[] = 'i.due_at IS NOT NULL AND i.due_at <= ?';
            $params[] = $filter->dueTo;
        }

        if ($filter->totalMin !== null) {
            $clauses[] = 'i.total_cents >= ?';
            $params[] = $filter->totalMin;
        }

        if ($filter->totalMax !== null) {
            $clauses[] = 'i.total_cents <= ?';
            $params[] = $filter->totalMax;
        }

        if ($filter->overdueOnly) {
            $clauses[] = "i.status IN ('issued', 'partially_paid')";
            $clauses[] = 'i.due_at IS NOT NULL AND i.due_at < ?';
            $params[] = $filter->todayOrNow();
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Maps a whitelisted sort field to a SQL ORDER BY, with `i.id` as tiebreak. */
    private static function orderByClause(InvoiceSort $sort): string
    {
        $columns = [
            'number'    => 'i.invoice_number',
            'client'    => 'c.name',
            'status'    => 'i.status',
            'issued_at' => 'i.issued_at',
            'due_at'    => 'i.due_at',
            'total'     => 'i.total_cents',
        ];

        $direction = $sort->descending ? 'DESC' : 'ASC';

        if ($sort->field === null || !isset($columns[$sort->field])) {
            return 'i.id ' . $direction;
        }

        return $columns[$sort->field] . ' ' . $direction . ', i.id DESC';
    }

    /** Escapes LIKE wildcards (ESCAPE '!') so user input is matched literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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

    public function findIssuedForExport(): array
    {
        $rows = $this->query->fetchAll(
            'SELECT i.invoice_number, i.issued_at, i.due_at,
                    COALESCE(c.name, \'\') AS client_name,
                    i.subtotal_cents, i.tax_cents, i.total_cents,
                    i.status, i.is_qualified_invoice
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id AND c.is_deleted = 0
             WHERE i.organization_id = ? AND i.is_deleted = 0
               AND i.status != \'draft\'
             ORDER BY i.issued_at DESC, i.id DESC',
            [$this->orgId->get()],
        );

        return array_map(static fn (array $row): array => [
            'invoice_number'     => (string) ($row['invoice_number'] ?? ''),
            'issued_at'          => isset($row['issued_at']) && $row['issued_at'] !== '' ? substr((string) $row['issued_at'], 0, 10) : '',
            'due_at'             => isset($row['due_at']) && $row['due_at'] !== '' ? substr((string) $row['due_at'], 0, 10) : '',
            'client_name'        => (string) ($row['client_name'] ?? ''),
            'subtotal_cents'     => (int) $row['subtotal_cents'],
            'tax_cents'          => (int) $row['tax_cents'],
            'total_cents'        => (int) $row['total_cents'],
            'status'             => (string) $row['status'],
            'is_qualified_invoice' => (bool) $row['is_qualified_invoice'],
        ], $rows);
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
