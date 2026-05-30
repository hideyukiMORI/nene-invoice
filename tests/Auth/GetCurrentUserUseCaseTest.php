<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\GetCurrentUserUseCase;
use NeneInvoice\Auth\Role;
use NeneInvoice\User\User;
use NeneInvoice\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetCurrentUserUseCaseTest extends TestCase
{
    public function test_returns_user_for_known_id(): void
    {
        $useCase = new GetCurrentUserUseCase($this->repositoryWith(7));

        $user = $useCase->execute(7);

        self::assertNotNull($user);
        self::assertSame('admin@example.com', $user->email);
        self::assertSame(Role::Admin, $user->role);
    }

    public function test_returns_null_for_unknown_id(): void
    {
        $useCase = new GetCurrentUserUseCase($this->repositoryWith(7));

        self::assertNull($useCase->execute(999));
    }

    private function repositoryWith(int $id): UserRepositoryInterface
    {
        $user = new User(
            email: 'admin@example.com',
            passwordHash: 'hashed',
            role: Role::Admin,
            organizationId: 1,
            status: 'active',
            id: $id,
        );

        return new class ($user) implements UserRepositoryInterface {
            public function __construct(private readonly User $user)
            {
            }

            public function findById(int $id): ?User
            {
                return $this->user->id === $id ? $this->user : null;
            }

            public function findByEmail(string $email): ?User
            {
                return $this->user->email === $email ? $this->user : null;
            }

            public function findInOrganization(int $id): ?User
            {
                return $this->user->id === $id ? $this->user : null;
            }

            /** @return list<User> */
            public function findAll(int $limit, int $offset): array
            {
                return [];
            }

            public function count(): int
            {
                return 0;
            }

            public function save(User $user): int
            {
                return 0;
            }

            public function update(User $user): void
            {
            }

            public function delete(int $id): void
            {
            }
        };
    }
}
