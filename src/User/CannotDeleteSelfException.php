<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use DomainException;

final class CannotDeleteSelfException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot delete your own account.');
    }
}
