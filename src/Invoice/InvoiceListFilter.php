<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

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
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->statuses === []
            && $this->clientId === null
            && $this->dueBefore === null
            && $this->dueAfter === null
            && !$this->overdueOnly
            && !$this->outstandingOnly;
    }

    public function todayOrNow(): string
    {
        return $this->today ?? date('Y-m-d');
    }
}
