<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

interface RecurringInvoiceRepositoryInterface
{
    public function save(RecurringInvoice $schedule): int;

    public function findById(int $id): ?RecurringInvoice;

    /** @return list<RecurringInvoice> */
    public function findByOrganization(int $limit, int $offset): array;

    public function countByOrganization(): int;

    /**
     * Active, non-deleted schedules whose next run is due on or before the given
     * JST calendar date (`Y-m-d`), oldest-due first. Org-scoped via the holder —
     * the request-time due check (Tier A) and a future all-org cron variant
     * (Tier B) build on this.
     *
     * @return list<RecurringInvoice>
     */
    public function findDue(string $onDate): array;

    public function update(RecurringInvoice $schedule): void;

    public function delete(int $id): void;
}
