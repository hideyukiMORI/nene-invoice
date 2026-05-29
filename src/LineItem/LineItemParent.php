<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * The document type a line item belongs to (polymorphic parent).
 */
enum LineItemParent: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
}
