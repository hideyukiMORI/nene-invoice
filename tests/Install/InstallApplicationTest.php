<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Install;

use NeneInvoice\Auth\Role;
use NeneInvoice\Install\InstallApplication;
use NeneInvoice\Install\InstallConfig;
use NeneInvoice\Install\InstallProvisioningRepositoryInterface;
use NeneInvoice\Organization\CreateOrganizationInput;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use NeneInvoice\Organization\OrganizationSlugConflictException;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;
use PHPUnit\Framework\TestCase;

/**
 * The first-run orchestration extracted out of `public_html/install.php` (S3-2).
 * These prove the two install shapes and their idempotent re-run behaviour
 * without touching a database — the Pdo insert layer is covered separately by
 * {@see PdoInstallProvisioningRepositoryTest}.
 */
final class InstallApplicationTest extends TestCase
{
    public function test_single_tenant_creates_org_admin_then_seeds_company_settings(): void
    {
        $createOrganization = $this->createMock(CreateOrganizationUseCaseInterface::class);
        $createOrganization->expects(self::once())
            ->method('execute')
            ->with(
                null,
                self::callback(static fn (CreateOrganizationInput $in): bool => $in->name === 'Acme'
                    && $in->slug === 'acme'
                    && $in->adminEmail === 'owner@example.com'
                    && $in->adminPassword === 'password123'),
            )
            ->willReturn(new Organization(name: 'Acme', slug: 'acme', plan: 'free', isActive: true, id: 42));

        $organizations = $this->createStub(OrganizationRepositoryInterface::class);

        $provisioning = $this->createMock(InstallProvisioningRepositoryInterface::class);
        $provisioning->expects(self::once())->method('seedCompanySettings')->with(42, 'Acme');
        $provisioning->expects(self::never())->method('createInitialSuperadmin');

        $result = (new InstallApplication($createOrganization, $organizations, $provisioning))
            ->install(new InstallConfig(
                isSingle: true,
                organizationName: 'Acme',
                organizationSlug: 'acme',
                adminEmail: 'owner@example.com',
                adminPassword: 'password123',
            ));

        self::assertSame(42, $result->organizationId);
        self::assertTrue($result->organizationCreated);
        self::assertTrue($result->adminCreated);
        self::assertTrue($result->isSingle);
        self::assertSame('owner@example.com', $result->adminEmail);
    }

    public function test_single_tenant_slug_conflict_resolves_existing_org_idempotently(): void
    {
        $createOrganization = $this->createStub(CreateOrganizationUseCaseInterface::class);
        $createOrganization->method('execute')
            ->willThrowException(new OrganizationSlugConflictException('acme'));

        $organizations = $this->createMock(OrganizationRepositoryInterface::class);
        $organizations->expects(self::once())
            ->method('findBySlug')
            ->with('acme')
            ->willReturn(new Organization(name: 'Acme', slug: 'acme', plan: 'free', isActive: true, id: 7));

        $provisioning = $this->createMock(InstallProvisioningRepositoryInterface::class);
        $provisioning->expects(self::never())->method('seedCompanySettings');

        $result = (new InstallApplication($createOrganization, $organizations, $provisioning))
            ->install(new InstallConfig(
                isSingle: true,
                organizationName: 'Acme',
                organizationSlug: 'acme',
                adminEmail: 'owner@example.com',
                adminPassword: 'password123',
            ));

        self::assertSame(7, $result->organizationId);
        self::assertFalse($result->organizationCreated);
        self::assertFalse($result->adminCreated);
        self::assertTrue($result->isSingle);
    }

    public function test_multi_tenant_creates_cross_tenant_superadmin_only(): void
    {
        $createOrganization = $this->createMock(CreateOrganizationUseCaseInterface::class);
        $createOrganization->expects(self::never())->method('execute');

        $organizations = $this->createStub(OrganizationRepositoryInterface::class);

        $provisioning = $this->createMock(InstallProvisioningRepositoryInterface::class);
        $provisioning->expects(self::once())
            ->method('createInitialSuperadmin')
            ->with('root@example.com', self::callback(static fn (string $hash): bool => $hash !== ''))
            ->willReturn(new User(email: 'root@example.com', passwordHash: 'x', role: Role::Superadmin, organizationId: null, id: 1));
        $provisioning->expects(self::never())->method('seedCompanySettings');

        $result = (new InstallApplication($createOrganization, $organizations, $provisioning))
            ->install(new InstallConfig(
                isSingle: false,
                organizationName: '',
                organizationSlug: '',
                adminEmail: 'root@example.com',
                adminPassword: 'password123',
            ));

        self::assertNull($result->organizationId);
        self::assertFalse($result->organizationCreated);
        self::assertTrue($result->adminCreated);
        self::assertFalse($result->isSingle);
    }

    public function test_multi_tenant_superadmin_email_conflict_is_idempotent(): void
    {
        $createOrganization = $this->createStub(CreateOrganizationUseCaseInterface::class);
        $organizations = $this->createStub(OrganizationRepositoryInterface::class);

        $provisioning = $this->createMock(InstallProvisioningRepositoryInterface::class);
        $provisioning->expects(self::once())
            ->method('createInitialSuperadmin')
            ->willThrowException(new UserEmailConflictException('root@example.com'));

        $result = (new InstallApplication($createOrganization, $organizations, $provisioning))
            ->install(new InstallConfig(
                isSingle: false,
                organizationName: '',
                organizationSlug: '',
                adminEmail: 'root@example.com',
                adminPassword: 'password123',
            ));

        self::assertFalse($result->adminCreated);
        self::assertNull($result->organizationId);
        self::assertFalse($result->isSingle);
    }
}
