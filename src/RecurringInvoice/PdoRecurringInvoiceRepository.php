<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoRecurringInvoiceRepository implements RecurringInvoiceRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, client_id, name, frequency, subtotal_cents, tax_cents, total_cents, next_run_on, last_run_on, is_active, notes, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
        private ClockInterface $clock,
    ) {
    }

    public function save(RecurringInvoice $schedule): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO recurring_invoices (organization_id, client_id, name, frequency, subtotal_cents, tax_cents, total_cents, next_run_on, last_run_on, is_active, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?, ?)',
            [
                $this->orgId->get(),
                $schedule->clientId,
                $schedule->name,
                $schedule->frequency->value,
                $schedule->subtotalCents,
                $schedule->taxCents,
                $schedule->totalCents,
                $schedule->nextRunOn,
                $schedule->lastRunOn,
                $schedule->isActive ? 1 : 0,
                $schedule->notes,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $id): ?RecurringInvoice
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM recurring_invoices WHERE id = ? AND organization_id = ? AND is_deleted = FALSE',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<RecurringInvoice> */
    public function findByOrganization(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM recurring_invoices
             WHERE organization_id = ? AND is_deleted = FALSE
             ORDER BY id DESC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): RecurringInvoice => $this->mapRow($row), $rows);
    }

    public function countByOrganization(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM recurring_invoices WHERE organization_id = ? AND is_deleted = FALSE',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /** @return list<RecurringInvoice> */
    public function findDue(string $onDate): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM recurring_invoices
             WHERE organization_id = ? AND is_deleted = FALSE AND is_active = TRUE AND next_run_on <= ?
             ORDER BY next_run_on ASC, id ASC',
            [$this->orgId->get(), $onDate],
        );

        return array_map(fn (array $row): RecurringInvoice => $this->mapRow($row), $rows);
    }

    public function update(RecurringInvoice $schedule): void
    {
        if ($schedule->id === null) {
            throw new RecurringInvoiceNotFoundException(0);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE recurring_invoices SET client_id = ?, name = ?, frequency = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ?, next_run_on = ?, last_run_on = ?, is_active = ?, notes = ?, updated_at = ?
             WHERE id = ? AND organization_id = ? AND is_deleted = FALSE',
            [
                $schedule->clientId,
                $schedule->name,
                $schedule->frequency->value,
                $schedule->subtotalCents,
                $schedule->taxCents,
                $schedule->totalCents,
                $schedule->nextRunOn,
                $schedule->lastRunOn,
                $schedule->isActive ? 1 : 0,
                $schedule->notes,
                $now,
                $schedule->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findById($schedule->id) === null) {
            throw new RecurringInvoiceNotFoundException($schedule->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new RecurringInvoiceNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE recurring_invoices SET is_deleted = TRUE, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [$this->clock->now()->format('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): RecurringInvoice
    {
        return new RecurringInvoice(
            organizationId: (int) $row['organization_id'],
            clientId: (int) $row['client_id'],
            name: (string) $row['name'],
            frequency: RecurringFrequency::from((string) $row['frequency']),
            subtotalCents: (int) $row['subtotal_cents'],
            taxCents: (int) $row['tax_cents'],
            totalCents: (int) $row['total_cents'],
            nextRunOn: (string) $row['next_run_on'],
            lastRunOn: isset($row['last_run_on']) && $row['last_run_on'] !== '' ? (string) $row['last_run_on'] : null,
            isActive: (bool) $row['is_active'],
            notes: isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
