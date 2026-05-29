<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use NeneInvoice\Auth\Role;

/**
 * An operator account.
 *
 * `organizationId` is the tenant the user belongs to. It is `null` only for
 * superadmin, which operates cross-tenant (ADR 0006). `status` is one of
 * `active` / `invited` (see `docs/explanation/terminology.md`).
 */
final readonly class User
{
    public function __construct(
        public string $email,
        public string $passwordHash,
        public Role $role,
        public ?int $organizationId = null,
        public string $status = 'active',
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
