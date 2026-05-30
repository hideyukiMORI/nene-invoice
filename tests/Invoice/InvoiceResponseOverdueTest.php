<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;
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
