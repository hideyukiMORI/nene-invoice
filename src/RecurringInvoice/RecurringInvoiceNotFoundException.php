<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use RuntimeException;

final class RecurringInvoiceNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Recurring invoice {$id} not found.");
    }
}
