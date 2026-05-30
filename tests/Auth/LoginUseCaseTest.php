<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use NeneInvoice\Auth\InvalidCredentialsException;
use NeneInvoice\Auth\LoginInput;
use NeneInvoice\Auth\LoginUseCase;
use NeneInvoice\Auth\Role;
use NeneInvoice\User\User;
use NeneInvoice\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class LoginUseCaseTest extends TestCase
{
    private const SECRET = 'test-secret';

    public function test_issues_token_with_identity_claims_on_valid_credentials(): void
    {
        $verifier = new LocalBearerTokenVerifier(self::SECRET);
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), $verifier);

        $output = $useCase->execute(new LoginInput('admin@example.com', 'correct-horse'));

        $claims = $verifier->verify($output->token);
        self::assertSame(7, $claims['sub'] ?? null);
        self::assertSame('admin', $claims['role'] ?? null);
        self::assertSame(1, $claims['org'] ?? null);
    }

    public function test_rejects_wrong_password(): void
    {
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), new LocalBearerTokenVerifier(self::SECRET));

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('admin@example.com', 'wrong'));
    }

    public function test_rejects_unknown_email(): void
    {
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), new LocalBearerTokenVerifier(self::SECRET));

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('nobody@example.com', 'correct-horse'));
    }

    private function repositoryWithUser(string $plainPassword): UserRepositoryInterface
    {
        $user = new User(
            email: 'admin@example.com',
            passwordHash: password_hash($plainPassword, PASSWORD_DEFAULT),
            role: Role::Admin,
            organizationId: 1,
            status: 'active',
            id: 7,
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
