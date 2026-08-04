<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Support\Jst;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that InvoiceResponse::toArray sets is_overdue correctly.
 *
 * is_overdue is true iff status is issued/partially_paid AND due_at is in the past.
 */
final class InvoiceResponseOverdueTest extends TestCase
{
    public function test_issued_with_past_due_at_is_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, '2020-01-01 00:00:00');
        $data    = InvoiceResponse::toArray($invoice);

        self::assertTrue($data['is_overdue']);
    }

    public function test_partially_paid_with_past_due_at_is_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::PartiallyPaid, '2020-01-01 00:00:00');
        $data    = InvoiceResponse::toArray($invoice);

        self::assertTrue($data['is_overdue']);
    }

    public function test_issued_with_future_due_at_is_not_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, '2099-12-31 23:59:59');
        $data    = InvoiceResponse::toArray($invoice);

        self::assertFalse($data['is_overdue']);
    }

    public function test_issued_with_null_due_at_is_not_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, null);
        $data    = InvoiceResponse::toArray($invoice);

        self::assertFalse($data['is_overdue']);
    }

    public function test_draft_is_never_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft, '2020-01-01 00:00:00');
        $data    = InvoiceResponse::toArray($invoice);

        self::assertFalse($data['is_overdue']);
    }

    public function test_paid_is_never_overdue(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid, '2020-01-01 00:00:00');
        $data    = InvoiceResponse::toArray($invoice);

        self::assertFalse($data['is_overdue']);
    }

    /**
     * `$nowJst` (Issue #752) makes the comparison deterministic. The flip point is
     * strict: an invoice is overdue only *after* its due instant has passed.
     */
    public function test_now_jst_pins_the_overdue_flip_point(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, '2026-08-05 00:00:00');

        self::assertFalse(InvoiceResponse::toArray($invoice, null, null, null, '2026-08-04 23:59:59')['is_overdue']);
        self::assertFalse(InvoiceResponse::toArray($invoice, null, null, null, '2026-08-05 00:00:00')['is_overdue']);
        self::assertTrue(InvoiceResponse::toArray($invoice, null, null, null, '2026-08-05 00:00:01')['is_overdue']);
    }

    /**
     * The argument is a **JST** wall clock, not a UTC instant. At UTC 2026-08-04
     * 06:00 it is already 15:00 in Japan, so an invoice due at noon JST is overdue
     * — feeding the raw UTC string instead would wrongly report it as current.
     */
    public function test_now_jst_is_interpreted_as_jst_not_utc(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, '2026-08-04 12:00:00');
        $clock   = new FixedClock('2026-08-04T06:00:00Z');

        $jst = Jst::of($clock->now())->format('Y-m-d H:i:s');
        $utc = $clock->now()->format('Y-m-d H:i:s');

        self::assertTrue(InvoiceResponse::toArray($invoice, null, null, null, $jst)['is_overdue']);
        self::assertFalse(InvoiceResponse::toArray($invoice, null, null, null, $utc)['is_overdue']);
    }

    /**
     * Omitting the argument must keep the pre-#752 wall-clock behaviour byte for
     * byte. Due dates far from now are used so the assertion cannot straddle a
     * second boundary between the two calls.
     */
    public function test_omitting_now_jst_matches_the_wall_clock_path(): void
    {
        foreach (['2020-01-01 00:00:00', '2099-12-31 23:59:59', null] as $dueAt) {
            $invoice = $this->makeInvoice(InvoiceStatus::Issued, $dueAt);

            self::assertSame(
                InvoiceResponse::toArray($invoice, null, null, null, Jst::nowString()),
                InvoiceResponse::toArray($invoice),
            );
        }
    }

    /** Status still short-circuits: a paid invoice is never overdue, whatever the clock says. */
    public function test_now_jst_does_not_override_status_short_circuit(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid, '2026-08-05 00:00:00');

        self::assertFalse(InvoiceResponse::toArray($invoice, null, null, null, '2099-01-01 00:00:00')['is_overdue']);
    }

    private function makeInvoice(InvoiceStatus $status, ?string $dueAt): Invoice
    {
        return new Invoice(
            organizationId: 1,
            clientId: 1,
            status: $status,
            subtotalCents: 1000,
            taxCents: 100,
            totalCents: 1100,
            dueAt: $dueAt,
            id: 1,
        );
    }
}
