<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\User;

use NeneInvoice\Auth\Role;
use NeneInvoice\Tests\Support\InMemoryUserRepository;
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

    protected function setUp(): void
    {
        $this->repo = new InMemoryUserRepository();
    }

    public function test_create_hashes_password_and_forces_caller_organization(): void
    {
        $user = (new CreateUserUseCase($this->repo))->execute(1, new CreateUserInput('m@org1', 'secret', Role::Member));

        self::assertSame(1, $user->organizationId);
        self::assertSame(Role::Member, $user->role);
        self::assertNotSame('secret', $user->passwordHash);
        self::assertTrue(password_verify('secret', $user->passwordHash));
    }

    public function test_create_blocks_superadmin_role(): void
    {
        $this->expectException(RoleNotAssignableException::class);
        (new CreateUserUseCase($this->repo))->execute(1, new CreateUserInput('x@org1', 'secret', Role::Superadmin));
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $create = new CreateUserUseCase($this->repo);
        $create->execute(1, new CreateUserInput('dup@org1', 'secret', Role::Member));

        $this->expectException(UserEmailConflictException::class);
        $create->execute(1, new CreateUserInput('dup@org1', 'secret', Role::Admin));
    }

    public function test_update_blocks_cross_organization_target(): void
    {
        $otherOrgUser = $this->repo->save(new User('a@org2', 'h', Role::Member, 2));

        $this->expectException(UserNotFoundException::class);
        (new UpdateUserUseCase($this->repo))->execute(1, $otherOrgUser, new UpdateUserInput(Role::Admin, 'active'));
    }

    public function test_update_blocks_superadmin_escalation(): void
    {
        $id = $this->repo->save(new User('a@org1', 'h', Role::Member, 1));

        $this->expectException(RoleNotAssignableException::class);
        (new UpdateUserUseCase($this->repo))->execute(1, $id, new UpdateUserInput(Role::Superadmin, 'active'));
    }

    public function test_delete_blocks_self(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));

        $this->expectException(CannotDeleteSelfException::class);
        (new DeleteUserUseCase($this->repo))->execute(1, $caller, $caller);
    }

    public function test_delete_blocks_cross_organization_target(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));
        $otherOrgUser = $this->repo->save(new User('a@org2', 'h', Role::Member, 2));

        $this->expectException(UserNotFoundException::class);
        (new DeleteUserUseCase($this->repo))->execute(1, $caller, $otherOrgUser);
    }

    public function test_delete_removes_user_in_same_organization(): void
    {
        $caller = $this->repo->save(new User('me@org1', 'h', Role::Admin, 1));
        $target = $this->repo->save(new User('t@org1', 'h', Role::Member, 1));

        (new DeleteUserUseCase($this->repo))->execute(1, $caller, $target);

        self::assertNull($this->repo->findById($target));
    }
}
