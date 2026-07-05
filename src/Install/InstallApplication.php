<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

use LogicException;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use NeneInvoice\User\UserEmailConflictException;

/**
 * First-run onboarding for the Tier A web installer: turns a freshly migrated
 * database into a usable instance. Extracted out of `public_html/install.php`
 * (which used to do the inserts inline as raw SQL) so the provisioning is a
 * single, unit-tested seam.
 *
 * Two shapes, decided by {@see InstallConfig::$isSingle}:
 *  - single: an organization + its `company_settings` + an `admin` bound to it.
 *    Reuses {@see CreateOrganizationUseCaseInterface}, which creates the org and
 *    its initial admin in one transaction with an audit trail (ADR 0006 / 0008).
 *  - multi: a cross-tenant `superadmin` (organization_id = NULL); no org is
 *    created (the running app's superadmin provisions tenants later).
 *
 * Idempotent on the org slug / admin email so a concurrent double-submit resolves
 * the existing rows instead of erroring — the installer's re-install guard is the
 * primary defense, this is the second layer.
 */
final readonly class InstallApplication
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $createOrganization,
        private OrganizationRepositoryInterface $organizations,
        private InstallProvisioningRepositoryInterface $provisioning,
    ) {
    }

    public function install(InstallConfig $config): InstallResult
    {
        return $config->isSingle
            ? $this->installSingleTenant($config)
            : $this->installSuperadmin($config);
    }

    private function installSingleTenant(InstallConfig $config): InstallResult
    {
        try {
            // Creates the org AND its initial admin in one transaction; the admin
            // is bound to the just-created org id, never a request-scoped one.
            $organization = $this->createOrganization->execute(null, new CreateOrganizationInput(
                name: $config->organizationName,
                slug: $config->organizationSlug,
                adminEmail: $config->adminEmail,
                adminPassword: $config->adminPassword,
            ));

            $organizationId = $organization->id
                ?? throw new LogicException('Organization was created without an id.');

            $this->provisioning->seedCompanySettings($organizationId, $config->organizationName);

            return new InstallResult(
                organizationId: $organizationId,
                organizationCreated: true,
                adminEmail: $config->adminEmail,
                adminCreated: true,
                isSingle: true,
            );
        } catch (OrganizationSlugConflictException) {
            // Concurrent double-submit: the org already exists. Resolve it and
            // report "already existed" rather than provisioning a second time.
            $existing = $this->organizations->findBySlug($config->organizationSlug);

            if ($existing === null || $existing->id === null) {
                throw new LogicException('Organization slug conflict but the organization could not be resolved.');
            }

            return new InstallResult(
                organizationId: $existing->id,
                organizationCreated: false,
                adminEmail: $config->adminEmail,
                adminCreated: false,
                isSingle: true,
            );
        }
    }

    private function installSuperadmin(InstallConfig $config): InstallResult
    {
        $passwordHash = password_hash($config->adminPassword, PASSWORD_DEFAULT);

        try {
            $this->provisioning->createInitialSuperadmin($config->adminEmail, $passwordHash);
            $adminCreated = true;
        } catch (UserEmailConflictException) {
            // Concurrent double-submit: the superadmin already exists.
            $adminCreated = false;
        }

        return new InstallResult(
            organizationId: null,
            organizationCreated: false,
            adminEmail: $config->adminEmail,
            adminCreated: $adminCreated,
            isSingle: false,
        );
    }
}
