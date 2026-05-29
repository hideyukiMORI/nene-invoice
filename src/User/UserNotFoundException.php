<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("User {$id} not found.");
    }
}
