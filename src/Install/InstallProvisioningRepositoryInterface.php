<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;

/**
 * First-run bootstrap inserts that the ordinary org-scoped use cases cannot make.
 *
 * These are deliberately install-only, cross-tenant operations (like
 * {@see \NeneInvoice\Organization\InitialAdminRepositoryInterface}): seeding a
 * new org's company settings, and creating the very first `superadmin` — the
 * latter is refused by {@see \NeneInvoice\User\CreateUserUseCase} (superadmin is
 * not assignable through the tenant API, ADR 0006), so the installer needs its
 * own path.
 */
interface InstallProvisioningRepositoryInterface
{
    /**
     * Seeds the `company_settings` row (issuer profile) for a freshly created
     * organization with its legal name; every other field keeps its column
     * default until the admin fills the profile in.
     */
    public function seedCompanySettings(int $organizationId, string $legalName): void;

    /**
     * Inserts the cross-tenant first `superadmin` (organization_id = NULL).
     *
     * @throws UserEmailConflictException when the (globally unique) email is taken
     */
    public function createInitialSuperadmin(string $email, string $passwordHash): User;
}
