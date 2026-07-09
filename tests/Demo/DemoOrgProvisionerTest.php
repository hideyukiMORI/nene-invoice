<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\SlugConflictException;
use NeneInvoice\Demo\DemoOrgProvisioner;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use PHPUnit\Framework\TestCase;

/**
 * The provisioner is the product half of the NENE2 demo orchestration (#610):
 * it must reuse the existing create-organization use case unchanged, resolve
 * the demo admin's id at creation time (the `role = 'admin'` lookup lives here
 * and only here), and translate the product slug-conflict exception into the
 * framework's retryable {@see SlugConflictException}.
 */
final class DemoOrgProvisionerTest extends TestCase
{
    public function test_provisions_via_the_use_case_and_resolves_the_admin_id(): void
    {
        $createOrg = new class () implements CreateOrganizationUseCaseInterface {
            public ?CreateOrganizationInput $input = null;

            public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
            {
                $this->input = $input;

                return new Organization(
                    name: $input->name,
                    slug: $input->slug,
                    plan: $input->plan,
                    isActive: true,
                    id: 42,
                );
            }
        };

        $query = $this->createMock(DatabaseQueryExecutorInterface::class);
        $query->expects(self::once())
            ->method('fetchOne')
            ->with(self::stringContains('FROM users'), [42, 'admin'])
            ->willReturn(['id' => 7]);

        $provisioned = (new DemoOrgProvisioner($createOrg, $query))->provision('demo-abc12345', 'kensetsu');

        self::assertSame(42, $provisioned->orgId);
        self::assertSame('demo-abc12345', $provisioned->slug);
        self::assertSame(7, $provisioned->adminUserId);
        self::assertNotNull($createOrg->input);
        self::assertSame('株式会社山手建設', $createOrg->input->name);
        self::assertSame('admin@demo-abc12345.demo.local', $createOrg->input->adminEmail);
        self::assertSame('free', $createOrg->input->plan);
    }

    public function test_slug_conflict_becomes_the_retryable_framework_exception(): void
    {
        $createOrg = new class () implements CreateOrganizationUseCaseInterface {
            public function execute(?int $actorUserId, CreateOrganizationInput $input): Organization
            {
                throw new OrganizationSlugConflictException($input->slug);
            }
        };

        $query = $this->createMock(DatabaseQueryExecutorInterface::class);
        $query->expects(self::never())->method('fetchOne');

        $this->expectException(SlugConflictException::class);

        (new DemoOrgProvisioner($createOrg, $query))->provision('demo-abc12345', 'kensetsu');
    }
}
