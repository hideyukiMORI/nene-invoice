<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use RuntimeException;

final class BankTransactionNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $bankTransactionId)
    {
        parent::__construct(sprintf('Bank transaction %d was not found.', $bankTransactionId));
    }
}
