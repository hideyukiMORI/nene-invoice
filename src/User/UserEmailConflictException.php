<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use DomainException;
use Throwable;

final class UserEmailConflictException extends DomainException
{
    public function __construct(string $email, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('A user with email "%s" already exists.', $email), 0, $previous);
    }
}
