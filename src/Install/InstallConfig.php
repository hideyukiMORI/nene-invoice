<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

/**
 * The first-run inputs the web installer collects on its administrator step.
 *
 * `isSingle` decides the shape of what gets provisioned (see {@see InstallApplication}):
 * a single-tenant install creates an organization + company settings + an `admin`
 * bound to it, whereas a multi-tenant install creates a cross-tenant `superadmin`
 * (organization_id = NULL) and leaves org creation to the running app. For the
 * multi-tenant case `organizationName` / `organizationSlug` are unused.
 */
final readonly class InstallConfig
{
    public function __construct(
        public bool $isSingle,
        public string $organizationName,
        public string $organizationSlug,
        public string $adminEmail,
        public string $adminPassword,
    ) {
    }
}
