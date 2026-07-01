<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;

/**
 * Provisions the first admin of a freshly created organization.
 *
 * This is a superadmin cross-tenant operation (ADR 0006): the organization id is
 * always passed explicitly (the newly created org id), never taken from a
 * request-scoped org holder. It is intentionally separate from the org-scoped
 * {@see \NeneInvoice\User\UserRepositoryInterface}, whose `save()` forces the
 * caller's resolved org and must not be reused for cross-tenant creation.
 */
interface InitialAdminRepositoryInterface
{
    /**
     * Inserts an `admin` / `active` user bound to the given organization.
     *
     * @throws UserEmailConflictException when the (globally unique) email is taken
     */
    public function createInitialAdmin(int $organizationId, string $email, string $passwordHash): User;
}
