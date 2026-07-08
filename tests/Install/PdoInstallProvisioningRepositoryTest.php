<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Install;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Auth\Role;
use NeneInvoice\Install\PdoInstallProvisioningRepository;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\User\UserEmailConflictException;
use PHPUnit\Framework\TestCase;

/**
 * The installer's cross-tenant bootstrap inserts. Proves the superadmin lands
 * with organization_id = NULL / the fixed `superadmin` / `active` shape, that the
 * global email uniqueness surfaces as a clean {@see UserEmailConflictException},
 * and that company settings are seeded with the given legal name.
 */
final class PdoInstallProvisioningRepositoryTest extends TestCase
{
    private PdoDatabaseQueryExecutor $executor;
    private PdoInstallProvisioningRepository $repository;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo = $factory->create();

        foreach (['users', 'company_settings'] as $table) {
            $schema = file_get_contents(dirname(__DIR__, 2) . "/database/schema/{$table}.sql");
            self::assertIsString($schema);
            $pdo->exec($schema);
        }

        $this->executor = new PdoDatabaseQueryExecutor($factory, $pdo);
        $this->repository = new PdoInstallProvisioningRepository($this->executor, new FixedClock());
    }

    public function test_creates_cross_tenant_superadmin_with_null_org(): void
    {
        $user = $this->repository->createInitialSuperadmin('root@example.com', 'hashed');

        self::assertGreaterThan(0, $user->id);
        self::assertSame(Role::Superadmin, $user->role);
        self::assertNull($user->organizationId);
        self::assertSame('active', $user->status);

        $row = $this->executor->fetchOne('SELECT role, organization_id, status FROM users WHERE id = ?', [$user->id]);
        self::assertNotNull($row);
        self::assertSame('superadmin', (string) $row['role']);
        self::assertNull($row['organization_id']);
        self::assertSame('active', (string) $row['status']);
    }

    public function test_rejects_duplicate_superadmin_email(): void
    {
        $this->repository->createInitialSuperadmin('dup@example.com', 'hashed');

        $this->expectException(UserEmailConflictException::class);
        $this->repository->createInitialSuperadmin('dup@example.com', 'hashed2');
    }

    public function test_seeds_company_settings_with_legal_name(): void
    {
        $this->repository->seedCompanySettings(42, 'Acme Inc');

        $row = $this->executor->fetchOne('SELECT organization_id, legal_name FROM company_settings WHERE organization_id = ?', [42]);
        self::assertNotNull($row);
        self::assertSame(42, (int) $row['organization_id']);
        self::assertSame('Acme Inc', (string) $row['legal_name']);
    }
}
