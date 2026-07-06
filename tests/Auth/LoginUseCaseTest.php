<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Http\UtcClock;
use NeneInvoice\Auth\InvalidCredentialsException;
use NeneInvoice\Auth\LoginInput;
use NeneInvoice\Auth\LoginUseCase;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\Role;
use NeneInvoice\Auth\TooManyLoginAttemptsException;
use NeneInvoice\Tests\Support\InMemoryLoginThrottle;
use NeneInvoice\Tests\Support\InMemoryRefreshTokenRepository;
use NeneInvoice\User\User;
use NeneInvoice\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class LoginUseCaseTest extends TestCase
{
    private const SECRET = 'test-secret';

    public function test_issues_token_with_identity_claims_on_valid_credentials(): void
    {
        $verifier = new LocalBearerTokenVerifier(self::SECRET);
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), $verifier, new InMemoryLoginThrottle(), $this->refreshIssuer(), new UtcClock());

        $output = $useCase->execute(new LoginInput('admin@example.com', 'correct-horse'));

        $claims = $verifier->verify($output->token);
        self::assertSame(7, $claims['sub'] ?? null);
        self::assertSame('admin', $claims['role'] ?? null);
        self::assertSame(1, $claims['org'] ?? null);
    }

    public function test_rejects_wrong_password(): void
    {
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), new LocalBearerTokenVerifier(self::SECRET), new InMemoryLoginThrottle(), $this->refreshIssuer(), new UtcClock());

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('admin@example.com', 'wrong'));
    }

    public function test_rejects_unknown_email(): void
    {
        $useCase = new LoginUseCase($this->repositoryWithUser('correct-horse'), new LocalBearerTokenVerifier(self::SECRET), new InMemoryLoginThrottle(), $this->refreshIssuer(), new UtcClock());

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('nobody@example.com', 'correct-horse'));
    }

    public function test_rejects_non_active_user_even_with_valid_password(): void
    {
        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse', 'disabled'),
            new LocalBearerTokenVerifier(self::SECRET),
            new InMemoryLoginThrottle(),
            $this->refreshIssuer(),
            new UtcClock(),
        );

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('admin@example.com', 'correct-horse'));
    }

    public function test_rejects_invited_user(): void
    {
        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse', 'invited'),
            new LocalBearerTokenVerifier(self::SECRET),
            new InMemoryLoginThrottle(),
            $this->refreshIssuer(),
            new UtcClock(),
        );

        $this->expectException(InvalidCredentialsException::class);
        $useCase->execute(new LoginInput('admin@example.com', 'correct-horse'));
    }

    public function test_throttles_after_too_many_failures_from_one_ip(): void
    {
        $throttle = new InMemoryLoginThrottle();
        for ($i = 0; $i < 10; $i++) {
            $throttle->recordFailure('203.0.113.7');
        }

        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse'),
            new LocalBearerTokenVerifier(self::SECRET),
            $throttle,
            $this->refreshIssuer(),
            new UtcClock(),
        );

        // Even with the correct password, the IP is over the failure ceiling.
        $this->expectException(TooManyLoginAttemptsException::class);
        $useCase->execute(new LoginInput('admin@example.com', 'correct-horse', '203.0.113.7'));
    }

    public function test_login_allowed_just_below_the_failure_ceiling(): void
    {
        $throttle = new InMemoryLoginThrottle();
        for ($i = 0; $i < 9; $i++) {
            $throttle->recordFailure('203.0.113.10');
        }

        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse'),
            new LocalBearerTokenVerifier(self::SECRET),
            $throttle,
            $this->refreshIssuer(),
            new UtcClock(),
        );

        // 9 failures is one below the ceiling of 10: the correct password
        // still authenticates (the block is `>= 10`, not `> 10`).
        $output = $useCase->execute(new LoginInput('admin@example.com', 'correct-horse', '203.0.113.10'));

        self::assertNotSame('', $output->token);
    }

    public function test_a_failed_attempt_is_recorded_against_the_ip(): void
    {
        $throttle = new InMemoryLoginThrottle();
        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse'),
            new LocalBearerTokenVerifier(self::SECRET),
            $throttle,
            $this->refreshIssuer(),
            new UtcClock(),
        );

        try {
            $useCase->execute(new LoginInput('admin@example.com', 'wrong', '203.0.113.8'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(1, $throttle->countFailuresSince('203.0.113.8', '1970-01-01 00:00:00'));
    }

    public function test_successful_login_clears_the_ip_failure_history(): void
    {
        $throttle = new InMemoryLoginThrottle();
        $throttle->recordFailure('203.0.113.9');

        $useCase = new LoginUseCase(
            $this->repositoryWithUser('correct-horse'),
            new LocalBearerTokenVerifier(self::SECRET),
            $throttle,
            $this->refreshIssuer(),
            new UtcClock(),
        );

        $useCase->execute(new LoginInput('admin@example.com', 'correct-horse', '203.0.113.9'));

        self::assertSame(0, $throttle->countFailuresSince('203.0.113.9', '1970-01-01 00:00:00'));
    }

    private function refreshIssuer(): RefreshTokenIssuer
    {
        return new RefreshTokenIssuer(new InMemoryRefreshTokenRepository(), new UtcClock());
    }

    private function repositoryWithUser(string $plainPassword, string $status = 'active'): UserRepositoryInterface
    {
        $user = new User(
            email: 'admin@example.com',
            passwordHash: password_hash($plainPassword, PASSWORD_DEFAULT),
            role: Role::Admin,
            organizationId: 1,
            status: $status,
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
