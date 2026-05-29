<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use NeneInvoice\Auth\Role;

final readonly class UpdateUserInput
{
    public function __construct(
        public Role $role,
        public string $status,
        public ?string $password = null,
    ) {
    }
}
