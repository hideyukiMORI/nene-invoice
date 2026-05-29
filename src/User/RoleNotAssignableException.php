<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use DomainException;
use NeneInvoice\Auth\Role;

final class RoleNotAssignableException extends DomainException
{
    public function __construct(Role $role)
    {
        parent::__construct(sprintf('The role "%s" cannot be assigned through user management.', $role->value));
    }
}
