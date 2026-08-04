<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use Nene2\Http\RequestScopedHolder;
use Nene2\Http\UtcClock;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceSort;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Support\Jst;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Pins the reference date `overdueOnly` resolves against (Issue #752).
 *
 * `todayOrNow()` used to read the wall clock via `Jst::today()`. It now takes a
 * {@see \Nene2\Http\ClockInterface}, so the JST calendar-day boundary — UTC
 * 15:00, where the Japanese date rolls over — is assertable instead of being
 * whatever day the suite happened to run on.
 */
final class InvoiceListFilterClockTest extends TestCase
{
    /** JST midnight is UTC 15:00 the previous day; one second earlier is still the old JST day. */
    public function test_reference_date_is_the_jst_day_just_before_the_boundary(): void
    {
        $filter = new InvoiceListFilter(overdueOnly: true);

        self::assertSame('2026-08-04', $filter->todayOrNow(new FixedClock('2026-08-04T14:59:59Z')));
    }

    public function test_reference_date_rolls_over_at_utc_1500(): void
    {
        $filter = new InvoiceListFilter(overdueOnly: true);

        self::assertSame('2026-08-05', $filter->todayOrNow(new FixedClock('2026-08-04T15:00:00Z')));
    }

    /** Reading the same instant as UTC would still say 08-04 — that is the bug this guards. */
    public function test_reference_date_is_jst_not_utc(): void
    {
        $clock  = new FixedClock('2026-08-04T15:00:00Z');
        $filter = new InvoiceListFilter(overdueOnly: true);

        self::assertSame('2026-08-05', $filter->todayOrNow($clock));
        self::assertNotSame($clock->now()->format('Y-m-d'), $filter->todayOrNow($clock));
    }

    /** An explicit `today` is a caller override and must win over the clock. */
    public function test_explicit_today_overrides_the_clock(): void
    {
        $filter = new InvoiceListFilter(overdueOnly: true, today: '2020-01-01');

        self::assertSame('2020-01-01', $filter->todayOrNow(new FixedClock('2026-08-04T15:00:00Z')));
    }

    /** Production passes UtcClock, which must reproduce the previous `Jst::today()` exactly. */
    public function test_utc_clock_reproduces_the_previous_wall_clock_behaviour(): void
    {
        $filter = new InvoiceListFilter(overdueOnly: true);

        self::assertSame(Jst::today(), $filter->todayOrNow(new UtcClock()));
    }

    /**
     * End to end through the filter: an invoice due 2026-08-04 is not yet overdue
     * on 2026-08-04 JST, and becomes overdue the instant the JST day rolls to 08-05
     * — even though UTC is still on 08-04.
     */
    public function test_overdue_only_flips_across_the_jst_day_boundary(): void
    {
        $filter = new InvoiceListFilter(overdueOnly: true);
        $sort   = InvoiceSort::fromInput(null, null);

        $before = $this->repositoryAt('2026-08-04T14:59:59Z');
        self::assertSame([], $before->findForAdminList($filter, $sort, 10, 0));
        self::assertSame(0, $before->countForAdminList($filter));

        $after = $this->repositoryAt('2026-08-04T15:00:00Z');
        self::assertCount(1, $after->findForAdminList($filter, $sort, 10, 0));
        self::assertSame(1, $after->countForAdminList($filter));
    }

    /** A repository holding one issued invoice due 2026-08-04, clocked at the given instant. */
    private function repositoryAt(string $instant): InMemoryInvoiceRepository
    {
        $holder = new RequestScopedHolder();
        $holder->set(1);

        $repository = new InMemoryInvoiceRepository($holder, new FixedClock($instant));
        $repository->save(new Invoice(
            organizationId: 1,
            clientId: 1,
            status: InvoiceStatus::Issued,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
            invoiceNumber: 'INV-2026-0001',
            dueAt: '2026-08-04',
        ));

        return $repository;
    }
}
