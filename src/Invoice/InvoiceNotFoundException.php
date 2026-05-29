<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use RuntimeException;

final class InvoiceNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Invoice {$id} not found.");
    }
}
