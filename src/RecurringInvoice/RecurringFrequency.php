<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * How often a {@see RecurringInvoice} schedule generates a new invoice
 * (#503). String values are registered in `docs/explanation/terminology.md` §2.
 * Date advancement (next_run_on math) lives in the generation use case so the
 * month-end edge is handled in one place, not here.
 */
enum RecurringFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
}
