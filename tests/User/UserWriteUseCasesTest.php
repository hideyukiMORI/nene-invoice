<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\User;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Auth\Role;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryUserRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use NeneInvoice\User\CannotDeleteSelfException;
use NeneInvoice\User\CreateUserInput;
use NeneInvoice\User\CreateUserUseCase;
use NeneInvoice\User\DeleteUserUseCase;
use NeneInvoice\User\RoleNotAssignableException;
use NeneInvoice\User\UpdateUserInput;
use NeneInvoice\User\UpdateUserUseCase;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;
use NeneInvoice\User\UserNotFoundException;
use PHPUnit\Framework\TestCase;

final class UserWriteUseCasesTest extends TestCase
{
    private InMemoryUserRepository $repo;
    private RecordingAuditRecorder $audit;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new InMemoryUserRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
    }

    public function test_create_hashes_password_forces_org_and_audits(): void
    {
        $user = (new CreateUserUseCase(new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute(99, new CreateUserInput('m@org1', 'secret', Role::Member));

        self::assertSame(1, $user->organizationId);
        self::assertSame(Role::Member, $user->role);
        self::assertTrue(password_verify('secret', $user->passwordHash));

        self::assertSame('user.created', $this->audit->records[0]['action']);
        self::assertSame(99, $this->audit->records[0]['actor_user_id']);
        // The audit snapshot must not leak the password hash.
        self::assertArrayNotHasKey('password_hash', $this->audit->records[0]['after']);
    }

    public function test_create_blocks_superadmin_role(): void
    {
        $this->expectException(RoleNotAssignableException::class);
        (new CreateUserUseCase(new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute(1, new CreateUserInput('x@org1', 'secret', Role::Superadmin));
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $create = new CreateUserUseCase(new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder);
        $create->execute(1, new CreateUserInput('dup@org1', 'secret', Role::Member));

        $this->expectException(UserEmailConflictException::class);
        $create->execute(1, new CreateUserInput('dup@org1', 'secret', Role::Admin));
    }

    public function test_update_records_before_and_after(): void
    {
        $id = $this->repo->save(new User('a@org1', 'h', Role::Member, 1));

        (new UpdateUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute(7, $id, new UpdateUserInput(Role::Admin, 'active'));

        self::assertSame('user.updated', $this->audit->records[0]['action']);
        self::assertSame('member', $this->audit->records[0]['before']['role'] ?? null);
        self::assertSame('admin', $this->audit->records[0]['after']['role'] ?? null);
    }

    public function test_update_blocks_cross_organization_target(): void
    {
        $otherOrgUser = $this->repo->save(new User('a@org2', 'h', Role::Member, 2));

        $this->expectException(UserNotFoundException::class);
        (new UpdateUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute(1, $otherOrgUser, new UpdateUserInput(Role::Admin, 'active'));
    }

    public function test_update_blocks_superadmin_escalation(): void
    {
        $id = $this->repo->save(new User('a@org1', 'h', Role::Member, 1));

        $this->expectException(RoleNotAssignableException::class);
        (new UpdateUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute(1, $id, new UpdateUserInput(Role::Superadmin, 'active'));
    }

    public function test_delete_blocks_self(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));

        $this->expectException(CannotDeleteSelfException::class);
        (new DeleteUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute($caller, $caller);
    }

    public function test_delete_blocks_cross_organization_target(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));
        $otherOrgUser = $this->repo->save(new User('a@org2', 'h', Role::Member, 2));

        $this->expectException(UserNotFoundException::class);
        (new DeleteUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute($caller, $otherOrgUser);
    }

    public function test_delete_removes_user_and_audits(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));
        $target = $this->repo->save(new User('t@org1', 'h', Role::Member, 1));

        (new DeleteUserUseCase($this->repo, new ImmediateTransactionManager(), fn () => $this->repo, $this->audit, $this->holder))->execute($caller, $target);

        self::assertNull($this->repo->findById($target));
        self::assertSame('user.deleted', $this->audit->records[0]['action']);
        self::assertSame($caller, $this->audit->records[0]['actor_user_id']);
    }
}
