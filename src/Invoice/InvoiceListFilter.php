<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\ClockInterface;
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
        /** Admin issue-date range (YYYY-MM-DD, inclusive). The accounting-period axis. */
        public ?string $issuedFrom = null,
        public ?string $issuedTo = null,
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
            && $this->dueTo === null
            && $this->issuedFrom === null
            && $this->issuedTo === null;
    }

    /**
     * Reference JST calendar date for `overdueOnly`. Takes the clock from the
     * caller rather than reading the wall clock, so the date boundary (UTC
     * 15:00 = JST midnight) is deterministic under test. Passing a
     * {@see \Nene2\Http\UtcClock} reproduces the previous `Jst::today()`.
     */
    public function todayOrNow(ClockInterface $clock): string
    {
        return $this->today ?? Jst::of($clock->now())->format('Y-m-d');
    }
}
