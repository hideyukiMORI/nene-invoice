<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, invoice_id, amount_cents, paid_at, method, note, external_reference, idempotency_key, is_deleted, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(Payment $payment): int
    {
        $now = date('Y-m-d H:i:s');

        $this->query->execute(
            'INSERT INTO payments (organization_id, invoice_id, amount_cents, paid_at, method, note, external_reference, idempotency_key, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $payment->organizationId,
                $payment->invoiceId,
                $payment->amountCents,
                $payment->paidAt,
                $payment->method,
                $payment->note,
                $payment->externalReference,
                $payment->idempotencyKey,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findByIdempotencyKey(int $organizationId, string $idempotencyKey): ?Payment
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE organization_id = ? AND idempotency_key = ?',
            [$organizationId, $idempotencyKey],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findById(int $id): ?Payment
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** Voids a payment (soft delete). Idempotent: already-voided is a no-op. */
    public function markVoided(int $id): void
    {
        $this->query->execute(
            'UPDATE payments SET is_deleted = 1, deleted_at = ?, updated_at = ? WHERE id = ? AND is_deleted = 0',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id],
        );
    }

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE invoice_id = ? AND is_deleted = 0 ORDER BY paid_at ASC, id ASC',
            [$invoiceId],
        );

        return array_map(fn (array $row): Payment => $this->mapRow($row), $rows);
    }

    public function totalPaidForInvoice(int $invoiceId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COALESCE(SUM(amount_cents), 0) AS total FROM payments WHERE invoice_id = ? AND is_deleted = 0',
            [$invoiceId],
        );

        return $row !== null ? (int) $row['total'] : 0;
    }

    /**
     * @param list<int> $invoiceIds
     * @return array<int, int>
     */
    public function sumPaidForInvoices(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($invoiceIds), '?'));
        $rows = $this->query->fetchAll(
            'SELECT invoice_id, COALESCE(SUM(amount_cents), 0) AS total
             FROM payments WHERE is_deleted = 0 AND invoice_id IN (' . $placeholders . ')
             GROUP BY invoice_id',
            $invoiceIds,
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['invoice_id']] = (int) $row['total'];
        }

        return $totals;
    }

    public function outstandingTotalForOrganization(int $organizationId): int
    {
        $row = $this->query->fetchOne(
            'SELECT
                COALESCE(SUM(i.total_cents), 0) - COALESCE(SUM(CASE WHEN p.is_deleted = 0 THEN p.amount_cents ELSE 0 END), 0) AS outstanding
            FROM invoices i
            LEFT JOIN payments p ON p.invoice_id = i.id
            WHERE i.organization_id = ? AND i.is_deleted = 0 AND i.status IN (\'issued\', \'partially_paid\')',
            [$organizationId],
        );

        return $row !== null ? (int) $row['outstanding'] : 0;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Payment
    {
        return new Payment(
            organizationId: (int) $row['organization_id'],
            invoiceId: (int) $row['invoice_id'],
            amountCents: (int) $row['amount_cents'],
            paidAt: (string) $row['paid_at'],
            method: isset($row['method']) && $row['method'] !== '' ? (string) $row['method'] : null,
            note: isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
            externalReference: isset($row['external_reference']) && $row['external_reference'] !== '' ? (string) $row['external_reference'] : null,
            idempotencyKey: isset($row['idempotency_key']) && $row['idempotency_key'] !== '' ? (string) $row['idempotency_key'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
