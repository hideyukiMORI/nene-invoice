<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\User;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Auth\Role;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\User\PdoUserRepository;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;
use NeneInvoice\User\UserNotFoundException;
use PHPUnit\Framework\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private PdoUserRepository $repository;
    private PdoDatabaseQueryExecutor $executor;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

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
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repository = new PdoUserRepository($this->executor, $this->holder, new FixedClock());
    }

    public function test_finds_user_by_email_and_id_after_save(): void
    {
        $id = $this->repository->save(new User(
            email: 'admin@example.com',
            passwordHash: 'hashed',
            role: Role::Admin,
            organizationId: 1,
        ));

        self::assertGreaterThan(0, $id);

        $byEmail = $this->repository->findByEmail('admin@example.com');
        self::assertNotNull($byEmail);
        self::assertSame($id, $byEmail->id);
        self::assertSame(Role::Admin, $byEmail->role);
        self::assertSame(1, $byEmail->organizationId);
        self::assertSame('active', $byEmail->status);

        $byId = $this->repository->findById($id);
        self::assertNotNull($byId);
        self::assertSame('admin@example.com', $byId->email);
    }

    public function test_superadmin_may_have_null_organization(): void
    {
        // Superadmins are seeded directly (no org holder), not created through the
        // org-scoped save() path, so insert the row directly to exercise mapping.
        $now = '2026-05-29 00:00:00';
        $this->executor->execute(
            'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?)',
            ['root@example.com', 'hashed', Role::Superadmin->value, 'active', $now, $now],
        );
        $id = $this->executor->lastInsertId();

        $user = $this->repository->findById($id);
        self::assertNotNull($user);
        self::assertNull($user->organizationId);
        self::assertSame(Role::Superadmin, $user->role);
    }

    public function test_lists_and_counts_users_scoped_to_organization(): void
    {
        // save() forces the holder org, so set it per fixture.
        $this->holder->set(1);
        $this->repository->save(new User(email: 'a@org1', passwordHash: 'h', role: Role::Admin, organizationId: 1));
        $this->repository->save(new User(email: 'b@org1', passwordHash: 'h', role: Role::Member, organizationId: 1));
        $this->holder->set(2);
        $this->repository->save(new User(email: 'c@org2', passwordHash: 'h', role: Role::Member, organizationId: 2));

        $this->holder->set(1);
        self::assertSame(2, $this->repository->count());
        $this->holder->set(2);
        self::assertSame(1, $this->repository->count());

        $this->holder->set(1);
        $org1 = $this->repository->findAll(10, 0);
        self::assertCount(2, $org1);
        self::assertSame('a@org1', $org1[0]->email);
    }

    public function test_rejects_duplicate_email_when_saving(): void
    {
        $this->repository->save(new User(email: 'dup@example.com', passwordHash: 'h', role: Role::Admin, organizationId: 1));

        $this->expectException(UserEmailConflictException::class);
        $this->repository->save(new User(email: 'dup@example.com', passwordHash: 'h', role: Role::Member, organizationId: 1));
    }

    public function test_updates_user_role_and_status(): void
    {
        $id = $this->repository->save(new User(email: 'u@example.com', passwordHash: 'h', role: Role::Member, organizationId: 1, status: 'invited'));

        $this->repository->update(new User(
            email: 'u@example.com',
            passwordHash: 'h',
            role: Role::Admin,
            organizationId: 1,
            status: 'active',
            id: $id,
        ));

        $updated = $this->repository->findById($id);
        self::assertNotNull($updated);
        self::assertSame(Role::Admin, $updated->role);
        self::assertSame('active', $updated->status);
    }

    public function test_update_is_scoped_to_organization(): void
    {
        // A user in org 1 cannot be updated while the holder resolves org 2.
        $id = $this->repository->save(new User(email: 'scoped@org1', passwordHash: 'h', role: Role::Member, organizationId: 1));

        $this->holder->set(2);
        $this->expectException(UserNotFoundException::class);
        $this->repository->update(new User(
            email: 'scoped@org1',
            passwordHash: 'h',
            role: Role::Admin,
            organizationId: 1,
            status: 'active',
            id: $id,
        ));
    }

    public function test_throws_when_deleting_unknown_user(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->repository->delete(987654);
    }

    public function test_deletes_existing_user(): void
    {
        $id = $this->repository->save(new User(email: 'tmp@example.com', passwordHash: 'h', role: Role::Member, organizationId: 1));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->count());
    }
}
