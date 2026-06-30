<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use RuntimeException;

final class PayerAliasNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $payerAliasId)
    {
        parent::__construct(sprintf('Payer alias %d was not found.', $payerAliasId));
    }
}
