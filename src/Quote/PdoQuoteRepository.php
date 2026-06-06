<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Support\SqlLike;

final readonly class PdoQuoteRepository implements QuoteRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, client_id, quote_number, status, issued_at, valid_until, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function findById(int $id): ?Quote
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM quotes WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /**
     * Admin list: filtered + searched + sorted, joined with the client name.
     *
     * @return list<QuoteListRow>
     */
    public function findForAdminList(QuoteListFilter $filter, QuoteSort $sort, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT q.id, q.organization_id, q.client_id, q.quote_number, q.status, q.issued_at,
                    q.valid_until, q.subtotal_cents, q.tax_cents, q.total_cents, q.notes,
                    q.is_deleted, q.created_at, q.updated_at,
                    COALESCE(c.name, \'\') AS client_name
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id AND c.is_deleted = 0
             WHERE ' . $where . '
             ORDER BY ' . self::orderByClause($sort) . '
             LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map(
            fn (array $row): QuoteListRow => new QuoteListRow(
                $this->mapRow($row),
                (string) ($row['client_name'] ?? ''),
            ),
            $rows,
        );
    }

    public function countForAdminList(QuoteListFilter $filter): int
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id AND c.is_deleted = 0
             WHERE ' . $where,
            $params,
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * Admin WHERE: columns qualified with the `q` (quotes) / `c` (clients)
     * aliases because the admin query joins clients (search / sort by name).
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildAdminWhere(QuoteListFilter $filter): array
    {
        $clauses = ['q.organization_id = ?', 'q.is_deleted = 0'];
        /** @var list<int|string> $params */
        $params = [$this->orgId->get()];

        if ($filter->statuses !== []) {
            $placeholders = implode(', ', array_fill(0, count($filter->statuses), '?'));
            $clauses[] = 'q.status IN (' . $placeholders . ')';
            foreach ($filter->statuses as $status) {
                $params[] = $status;
            }
        }

        if ($filter->search !== null) {
            $clauses[] = "(q.quote_number LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!')";
            $like = '%' . SqlLike::escape($filter->search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($filter->validFrom !== null) {
            $clauses[] = 'q.valid_until IS NOT NULL AND q.valid_until >= ?';
            $params[] = $filter->validFrom;
        }

        if ($filter->validTo !== null) {
            $clauses[] = 'q.valid_until IS NOT NULL AND q.valid_until <= ?';
            $params[] = $filter->validTo;
        }

        if ($filter->totalMin !== null) {
            $clauses[] = 'q.total_cents >= ?';
            $params[] = $filter->totalMin;
        }

        if ($filter->totalMax !== null) {
            $clauses[] = 'q.total_cents <= ?';
            $params[] = $filter->totalMax;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Maps a whitelisted sort field to a SQL ORDER BY, with `q.id` as tiebreak. */
    private static function orderByClause(QuoteSort $sort): string
    {
        $columns = [
            'number'      => 'q.quote_number',
            'client'      => 'c.name',
            'status'      => 'q.status',
            'issued_at'   => 'q.issued_at',
            'valid_until' => 'q.valid_until',
            'total'       => 'q.total_cents',
        ];

        $direction = $sort->descending ? 'DESC' : 'ASC';

        if ($sort->field === null || !isset($columns[$sort->field])) {
            return 'q.id ' . $direction;
        }

        return $columns[$sort->field] . ' ' . $direction . ', q.id DESC';
    }

    public function save(Quote $quote): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO quotes (organization_id, client_id, quote_number, status, issued_at, valid_until, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $this->orgId->get(),
                $quote->clientId,
                $quote->quoteNumber,
                $quote->status->value,
                $quote->issuedAt,
                $quote->validUntil,
                $quote->subtotalCents,
                $quote->taxCents,
                $quote->totalCents,
                $quote->notes,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function update(Quote $quote): void
    {
        if ($quote->id === null) {
            throw new QuoteNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE quotes SET client_id = ?, status = ?, issued_at = ?, valid_until = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ?, notes = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [
                $quote->clientId,
                $quote->status->value,
                $quote->issuedAt,
                $quote->validUntil,
                $quote->subtotalCents,
                $quote->taxCents,
                $quote->totalCents,
                $quote->notes,
                $now,
                $quote->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findById($quote->id) === null) {
            throw new QuoteNotFoundException($quote->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new QuoteNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE quotes SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Quote
    {
        return new Quote(
            organizationId: (int) $row['organization_id'],
            clientId: (int) $row['client_id'],
            quoteNumber: (string) $row['quote_number'],
            status: QuoteStatus::from((string) $row['status']),
            subtotalCents: (int) $row['subtotal_cents'],
            taxCents: (int) $row['tax_cents'],
            totalCents: (int) $row['total_cents'],
            issuedAt: isset($row['issued_at']) && $row['issued_at'] !== '' ? (string) $row['issued_at'] : null,
            validUntil: isset($row['valid_until']) && $row['valid_until'] !== '' ? (string) $row['valid_until'] : null,
            notes: isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
