<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Support\Jst;

/**
 * Read filters for listing invoices (service API §2.1). All predicates resolve
 * against the invoices table alone — `overdueOnly` / `outstandingOnly` are
 * expressed via status (outstanding > 0 ⟺ status issued/partially_paid in our
 * model), so no payment join is needed and pagination stays correct.
 */
final readonly class InvoiceListFilter
{
    /**
     * @param list<string> $statuses subset of status values; empty = any
     */
    public function __construct(
        public array $statuses = [],
        public ?int $clientId = null,
        public ?string $dueBefore = null,
        public ?string $dueAfter = null,
        public bool $overdueOnly = false,
        public bool $outstandingOnly = false,
        /** Reference date for `overdueOnly` (YYYY-MM-DD); defaults to today. */
        public ?string $today = null,
        /** Admin search: matches invoice_number OR client name (substring). */
        public ?string $search = null,
        /** Admin total-amount range (integer cents, inclusive). */
        public ?int $totalMin = null,
        public ?int $totalMax = null,
        /** Admin due-date range (YYYY-MM-DD, inclusive). */
        public ?string $dueFrom = null,
        public ?string $dueTo = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->statuses === []
            && $this->clientId === null
            && $this->dueBefore === null
            && $this->dueAfter === null
            && !$this->overdueOnly
            && !$this->outstandingOnly
            && $this->search === null
            && $this->totalMin === null
            && $this->totalMax === null
            && $this->dueFrom === null
            && $this->dueTo === null;
    }

    public function todayOrNow(): string
    {
        return $this->today ?? Jst::today();
    }
}
