<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DisposableOrgProvisionerInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Demo\SlugConflictException;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\OrganizationSlugConflictException;

/**
 * Creates one disposable demo organization through the existing
 * {@see CreateOrganizationUseCaseInterface} — a thin wrapper, no new org-creation
 * path (Nene2\Demo consumer, #610).
 *
 * The demo admin is provisioned by the use case in the same transaction as the
 * org; the `role = 'admin'` lookup that identifies it lives here and only here
 * ({@see ProvisionedDemoOrg::$adminUserId}), so the orchestration never queries
 * by role literal. The read goes through the shared executor — no second
 * connection (SQLite "database is locked").
 */
final readonly class DemoOrgProvisioner implements DisposableOrgProvisionerInterface
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $createOrganization,
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function provision(string $slug, string $template): ProvisionedDemoOrg
    {
        // The handler validated the raw route parameter through
        // DemoTemplate::tryFromValue(), so this cannot fail.
        $demoTemplate = DemoTemplate::from($template);

        $input = new CreateOrganizationInput(
            name: $demoTemplate->companyName(),
            slug: $slug,
            plan: 'free',
            adminEmail: 'admin@' . $slug . '.demo.local',
            adminPassword: bin2hex(random_bytes(16)),
        );

        try {
            $organization = $this->createOrganization->execute(null, $input);
        } catch (OrganizationSlugConflictException $exception) {
            throw new SlugConflictException("Demo slug '{$slug}' is already taken.", previous: $exception);
        }

        $orgId = $organization->id ?? 0;
        $adminRow = $this->query->fetchOne(
            'SELECT id FROM users WHERE organization_id = ? AND role = ? ORDER BY id ASC LIMIT 1',
            [$orgId, 'admin'],
        );

        return new ProvisionedDemoOrg($orgId, $organization->slug, (int) ($adminRow['id'] ?? 0));
    }
}
