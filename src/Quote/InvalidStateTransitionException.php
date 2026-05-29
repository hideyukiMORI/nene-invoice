<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use DomainException;

final class InvalidStateTransitionException extends DomainException
{
    public function __construct(QuoteStatus $from, QuoteStatus $to)
    {
        parent::__construct(sprintf('A quote cannot transition from "%s" to "%s".', $from->value, $to->value));
    }
}
