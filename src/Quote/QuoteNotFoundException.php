<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use RuntimeException;

final class QuoteNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Quote {$id} not found.");
    }
}
