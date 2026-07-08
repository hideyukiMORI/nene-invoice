<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, invoice_id, amount_cents, paid_at, method, note, external_reference, idempotency_key, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
        private ClockInterface $clock,
    ) {
    }

    public function save(Payment $payment): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO payments (organization_id, invoice_id, amount_cents, paid_at, method, note, external_reference, idempotency_key, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?, ?)',
            [
                $this->orgId->get(),
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

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE organization_id = ? AND idempotency_key = ?',
            [$this->orgId->get(), $idempotencyKey],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findById(int $id): ?Payment
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** Voids a payment (soft delete). Idempotent: already-voided is a no-op. */
    public function markVoided(int $id): void
    {
        $this->query->execute(
            'UPDATE payments SET is_deleted = TRUE, deleted_at = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = FALSE',
            [$this->clock->now()->format('Y-m-d H:i:s'), $this->clock->now()->format('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE invoice_id = ? AND organization_id = ? AND is_deleted = FALSE ORDER BY paid_at ASC, id ASC',
            [$invoiceId, $this->orgId->get()],
        );

        return array_map(fn (array $row): Payment => $this->mapRow($row), $rows);
    }

    public function totalPaidForInvoice(int $invoiceId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COALESCE(SUM(amount_cents), 0) AS total FROM payments WHERE invoice_id = ? AND organization_id = ? AND is_deleted = FALSE',
            [$invoiceId, $this->orgId->get()],
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
             FROM payments WHERE is_deleted = FALSE AND organization_id = ? AND invoice_id IN (' . $placeholders . ')
             GROUP BY invoice_id',
            [$this->orgId->get(), ...$invoiceIds],
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['invoice_id']] = (int) $row['total'];
        }

        return $totals;
    }

    public function outstandingTotal(): int
    {
        $row = $this->query->fetchOne(
            'SELECT
                COALESCE(SUM(i.total_cents), 0) - COALESCE(SUM(CASE WHEN p.is_deleted = FALSE THEN p.amount_cents ELSE 0 END), 0) AS outstanding
            FROM invoices i
            LEFT JOIN payments p ON p.invoice_id = i.id
            WHERE i.organization_id = ? AND i.is_deleted = FALSE AND i.status IN (\'issued\', \'partially_paid\')',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['outstanding'] : 0;
    }

    public function findValidForExport(): array
    {
        $rows = $this->query->fetchAll(
            'SELECT p.paid_at, p.amount_cents, p.method, p.note,
                    COALESCE(i.invoice_number, \'\') AS invoice_number,
                    COALESCE(c.name, \'\') AS client_name
             FROM payments p
             LEFT JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN clients c ON c.id = i.client_id AND c.is_deleted = FALSE
             WHERE p.organization_id = ? AND p.is_deleted = FALSE
             ORDER BY p.paid_at DESC, p.id DESC',
            [$this->orgId->get()],
        );

        return array_map(static fn (array $row): array => [
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'client_name'    => (string) ($row['client_name'] ?? ''),
            'paid_at'        => substr((string) ($row['paid_at'] ?? ''), 0, 10),
            'amount_cents'   => (int) $row['amount_cents'],
            'method'         => (string) ($row['method'] ?? ''),
            'note'           => (string) ($row['note'] ?? ''),
        ], $rows);
    }

    public function receivedTotalBetween(string $startInclusive, string $endExclusive): int
    {
        $row = $this->query->fetchOne(
            'SELECT COALESCE(SUM(amount_cents), 0) AS total
             FROM payments
             WHERE organization_id = ? AND is_deleted = FALSE AND paid_at >= ? AND paid_at < ?',
            [$this->orgId->get(), $startInclusive, $endExclusive],
        );

        return $row !== null ? (int) $row['total'] : 0;
    }

    public function agingBuckets(string $now, string $thirtyDaysAgo): array
    {
        // Per-invoice net outstanding (total - non-void payments), then bucket the
        // positive remainder by how far past due_at the invoice is.
        $row = $this->query->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN t.due_at IS NULL OR t.due_at >= ? THEN t.net ELSE 0 END), 0) AS current,
                COALESCE(SUM(CASE WHEN t.due_at < ? AND t.due_at >= ? THEN t.net ELSE 0 END), 0) AS overdue_1_30,
                COALESCE(SUM(CASE WHEN t.due_at < ? THEN t.net ELSE 0 END), 0) AS overdue_31_plus
             FROM (
                SELECT i.id, i.due_at,
                       i.total_cents - COALESCE(SUM(CASE WHEN p.is_deleted = FALSE THEN p.amount_cents ELSE 0 END), 0) AS net
                FROM invoices i
                LEFT JOIN payments p ON p.invoice_id = i.id
                WHERE i.organization_id = ? AND i.is_deleted = FALSE AND i.status IN (\'issued\', \'partially_paid\')
                GROUP BY i.id, i.due_at, i.total_cents
             ) t
             WHERE t.net > 0',
            [$now, $now, $thirtyDaysAgo, $thirtyDaysAgo, $this->orgId->get()],
        );

        return [
            'current'         => $row !== null ? (int) $row['current'] : 0,
            'overdue_1_30'    => $row !== null ? (int) $row['overdue_1_30'] : 0,
            'overdue_31_plus' => $row !== null ? (int) $row['overdue_31_plus'] : 0,
        ];
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
