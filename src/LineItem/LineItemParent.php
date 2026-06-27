<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * The parent a line item belongs to (polymorphic). Quotes and invoices are
 * documents; templates (#329) are reusable line presets that store their rows
 * here too, but never feed document totals or history suggestions. Recurring
 * invoices (#503) store their line *template* here, copied to a fresh draft
 * invoice each period by the generation use case.
 */
enum LineItemParent: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Template = 'template';
    case RecurringInvoice = 'recurring_invoice';
}
