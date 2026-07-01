<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\Auth\Role;
use NeneInvoice\Organization\PdoInitialAdminRepository;
use NeneInvoice\User\UserEmailConflictException;
use PHPUnit\Framework\TestCase;

/**
 * The cross-tenant initial-admin insert. Unlike {@see \NeneInvoice\User\PdoUserRepository},
 * the organization id is supplied explicitly (never from a request holder), so
 * these tests prove the row lands in the requested org with the fixed
 * `admin` / `active` shape, and that the global email uniqueness surfaces as a
 * clean {@see UserEmailConflictException}.
 */
final class PdoInitialAdminRepositoryTest extends TestCase
{
    private PdoDatabaseQueryExecutor $executor;
    private PdoInitialAdminRepository $repository;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/users.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->executor = new PdoDatabaseQueryExecutor($factory, $pdo);
        $this->repository = new PdoInitialAdminRepository($this->executor);
    }

    public function test_creates_admin_bound_to_the_given_organization(): void
    {
        $admin = $this->repository->createInitialAdmin(7, 'owner@beta.example', 'hashed');

        self::assertGreaterThan(0, $admin->id);
        self::assertSame(Role::Admin, $admin->role);
        self::assertSame(7, $admin->organizationId);
        self::assertSame('active', $admin->status);

        $row = $this->executor->fetchOne('SELECT role, organization_id, status FROM users WHERE id = ?', [$admin->id]);
        self::assertNotNull($row);
        self::assertSame('admin', (string) $row['role']);
        self::assertSame(7, (int) $row['organization_id']);
        self::assertSame('active', (string) $row['status']);
    }

    public function test_rejects_duplicate_email_across_organizations(): void
    {
        $this->repository->createInitialAdmin(1, 'dup@example.com', 'hashed');

        // users.email is globally UNIQUE — even a different org must conflict.
        $this->expectException(UserEmailConflictException::class);
        $this->repository->createInitialAdmin(2, 'dup@example.com', 'hashed');
    }
}
