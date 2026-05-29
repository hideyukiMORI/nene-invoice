<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoQuoteRepository implements QuoteRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, client_id, quote_number, status, issued_at, valid_until, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?Quote
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM quotes WHERE id = ? AND is_deleted = 0',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Quote> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM quotes WHERE organization_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT ? OFFSET ?',
            [$organizationId, $limit, $offset],
        );

        return array_map(fn (array $row): Quote => $this->mapRow($row), $rows);
    }

    public function countByOrganization(int $organizationId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM quotes WHERE organization_id = ? AND is_deleted = 0',
            [$organizationId],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Quote $quote): int
    {
        $now = date('Y-m-d H:i:s');

        $this->query->execute(
            'INSERT INTO quotes (organization_id, client_id, quote_number, status, issued_at, valid_until, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $quote->organizationId,
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
            'UPDATE quotes SET client_id = ?, status = ?, issued_at = ?, valid_until = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ?, notes = ?, updated_at = ? WHERE id = ? AND is_deleted = 0',
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
            'UPDATE quotes SET is_deleted = 1, deleted_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id],
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
