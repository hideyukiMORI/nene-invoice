<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Install;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use NeneInvoice\Install\InstallApplication;
use NeneInvoice\Install\InstallConfig;
use NeneInvoice\Install\PdoInstallProvisioningRepository;
use NeneInvoice\Organization\CreateOrganizationUseCase;
use NeneInvoice\Organization\PdoInitialAdminRepository;
use NeneInvoice\Organization\PdoOrganizationRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see InstallApplication} against a real (file-backed) SQLite database
 * with the REAL {@see CreateOrganizationUseCase} (real repositories, transaction
 * manager and audit) plus the real Pdo provisioning repo — i.e. exactly the
 * collaboration `public_html/install.php` wires up, minus the container boot.
 *
 * Proves the rows the installer must land: single → organization + admin +
 * company_settings; multi → a cross-tenant superadmin and nothing else.
 */
final class InstallApplicationIntegrationTest extends TestCase
{
    private string $dbPath;
    private PdoConnectionFactory $factory;
    private PdoDatabaseQueryExecutor $executor;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nene_install_it_');
        self::assertIsString($path);
        $this->dbPath = $path;

        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: $this->dbPath,
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $this->factory = new PdoConnectionFactory($config);

        $pdo = $this->factory->create();
        foreach (['organizations.sql', 'users.sql', 'company_settings.sql'] as $file) {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/' . $file);
            self::assertIsString($schema);
            $pdo->exec($schema);
        }

        $this->executor = new PdoDatabaseQueryExecutor($this->factory, $pdo);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function application(): InstallApplication
    {
        $createOrganization = new CreateOrganizationUseCase(
            new PdoDatabaseTransactionManager($this->factory),
            static fn ($exec) => new PdoOrganizationRepository($exec),
            static fn () => new RecordingAuditRecorder(),
            static fn ($exec) => new PdoInitialAdminRepository($exec),
        );

        return new InstallApplication(
            $createOrganization,
            new PdoOrganizationRepository($this->executor),
            new PdoInstallProvisioningRepository($this->executor),
        );
    }

    public function test_single_tenant_provisions_org_admin_and_company_settings(): void
    {
        $result = $this->application()->install(new InstallConfig(
            isSingle: true,
            organizationName: '山田商店',
            organizationSlug: 'yamada',
            adminEmail: 'owner@example.com',
            adminPassword: 'correct horse battery',
        ));

        self::assertTrue($result->organizationCreated);
        self::assertTrue($result->adminCreated);
        self::assertNotNull($result->organizationId);

        $org = $this->executor->fetchOne('SELECT name, slug, plan FROM organizations WHERE id = ?', [$result->organizationId]);
        self::assertNotNull($org);
        self::assertSame('山田商店', (string) $org['name']);
        self::assertSame('yamada', (string) $org['slug']);
        self::assertSame('free', (string) $org['plan']);

        $admin = $this->executor->fetchOne('SELECT role, organization_id, status, password_hash FROM users WHERE email = ?', ['owner@example.com']);
        self::assertNotNull($admin);
        self::assertSame('admin', (string) $admin['role']);
        self::assertSame($result->organizationId, (int) $admin['organization_id']);
        self::assertSame('active', (string) $admin['status']);
        self::assertTrue(password_verify('correct horse battery', (string) $admin['password_hash']));

        $company = $this->executor->fetchOne('SELECT legal_name FROM company_settings WHERE organization_id = ?', [$result->organizationId]);
        self::assertNotNull($company);
        self::assertSame('山田商店', (string) $company['legal_name']);
    }

    public function test_multi_tenant_provisions_cross_tenant_superadmin_without_org(): void
    {
        $result = $this->application()->install(new InstallConfig(
            isSingle: false,
            organizationName: '',
            organizationSlug: '',
            adminEmail: 'root@example.com',
            adminPassword: 'correct horse battery',
        ));

        self::assertNull($result->organizationId);
        self::assertFalse($result->organizationCreated);
        self::assertTrue($result->adminCreated);

        $superadmin = $this->executor->fetchOne('SELECT role, organization_id, status, password_hash FROM users WHERE email = ?', ['root@example.com']);
        self::assertNotNull($superadmin);
        self::assertSame('superadmin', (string) $superadmin['role']);
        self::assertNull($superadmin['organization_id']);
        self::assertSame('active', (string) $superadmin['status']);
        self::assertTrue(password_verify('correct horse battery', (string) $superadmin['password_hash']));

        // Multi-tenant install creates no organization or company settings.
        $orgCount = $this->executor->fetchOne('SELECT COUNT(*) AS c FROM organizations');
        self::assertNotNull($orgCount);
        self::assertSame(0, (int) $orgCount['c']);

        $companyCount = $this->executor->fetchOne('SELECT COUNT(*) AS c FROM company_settings');
        self::assertNotNull($companyCount);
        self::assertSame(0, (int) $companyCount['c']);
    }
}
