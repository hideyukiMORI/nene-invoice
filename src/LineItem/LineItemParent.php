<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * The parent a line item belongs to (polymorphic). Quotes and invoices are
 * documents; templates (#329) are reusable line presets that store their rows
 * here too, but never feed document totals or history suggestions.
 */
enum LineItemParent: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Template = 'template';
}
