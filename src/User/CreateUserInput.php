<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use NeneInvoice\Auth\Role;

final readonly class CreateUserInput
{
    public function __construct(
        public string $email,
        public string $password,
        public Role $role,
    ) {
    }
}
