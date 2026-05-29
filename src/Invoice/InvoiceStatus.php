<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Invoice lifecycle states (domain-model.md). `overdue` is a computed flag
 * (status is `issued`/`partially_paid` past `due_at`), not a stored status.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
}
