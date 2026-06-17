<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use NeneInvoice\Auth\InvalidRefreshTokenException;
use NeneInvoice\Auth\RefreshSessionUseCase;
use NeneInvoice\Auth\RefreshToken;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\RefreshTokenReuseException;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Auth\Role;
use NeneInvoice\Tests\Support\InMemoryRefreshTokenRepository;
use NeneInvoice\Tests\Support\InMemoryUserRepository;
use NeneInvoice\User\User;
use PHPUnit\Framework\TestCase;

final class RefreshSessionUseCaseTest extends TestCase
{
    private const SECRET = 'test-secret';

    public function test_rotates_token_and_mints_access_token_scoped_to_same_tenant(): void
    {
        $users = new InMemoryUserRepository();
        $userId = $users->save($this->user(organizationId: 5));
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $issuer = new RefreshTokenIssuer($refreshTokens);
        $verifier = new LocalBearerTokenVerifier(self::SECRET);
        $useCase = new RefreshSessionUseCase($refreshTokens, $users, $issuer, $verifier);

        $issued = $issuer->issue($userId, 5);

        $session = $useCase->execute($issued->rawToken);

        // Access token carries the same principal + tenant (no cross-tenant escalation).
        $claims = $verifier->verify($session->accessToken);
        self::assertSame($userId, $claims['sub'] ?? null);
        self::assertSame(5, $claims['org'] ?? null);

        // The presented token was rotated away for a brand-new one.
        self::assertNotSame($issued->rawToken, $session->refreshToken->rawToken);
        self::assertNotNull($refreshTokens->findByHash(RefreshTokenSecret::hash($session->refreshToken->rawToken)));
    }

    public function test_unknown_token_fails_closed(): void
    {
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $useCase = new RefreshSessionUseCase(
            $refreshTokens,
            new InMemoryUserRepository(),
            new RefreshTokenIssuer($refreshTokens),
            new LocalBearerTokenVerifier(self::SECRET),
        );

        $this->expectException(InvalidRefreshTokenException::class);
        $useCase->execute('not-a-real-token');
    }

    public function test_replaying_a_rotated_token_revokes_the_whole_family(): void
    {
        $users = new InMemoryUserRepository();
        $userId = $users->save($this->user(organizationId: 1));
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $issuer = new RefreshTokenIssuer($refreshTokens);
        $useCase = new RefreshSessionUseCase($refreshTokens, $users, $issuer, new LocalBearerTokenVerifier(self::SECRET));

        $issued = $issuer->issue($userId, 1);
        $rotated = $useCase->execute($issued->rawToken); // spend the first token

        // Replaying the spent token is treated as theft: reuse exception …
        try {
            $useCase->execute($issued->rawToken);
            self::fail('Expected reuse to be rejected.');
        } catch (RefreshTokenReuseException) {
            // expected
        }

        // … and the rotated sibling is now dead too (family revoked): presenting
        // a revoked token also fails closed (reported as reuse).
        $this->expectException(RefreshTokenReuseException::class);
        $useCase->execute($rotated->refreshToken->rawToken);
    }

    public function test_expired_token_fails_closed(): void
    {
        $users = new InMemoryUserRepository();
        $userId = $users->save($this->user(organizationId: 1));
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $raw = RefreshTokenSecret::generateRaw();
        $refreshTokens->create(new RefreshToken(
            userId: $userId,
            organizationId: 1,
            familyId: RefreshTokenSecret::generateFamilyId(),
            tokenHash: RefreshTokenSecret::hash($raw),
            issuedAt: '2020-01-01 00:00:00',
            expiresAt: '2020-01-08 00:00:00',
        ));
        $useCase = new RefreshSessionUseCase($refreshTokens, $users, new RefreshTokenIssuer($refreshTokens), new LocalBearerTokenVerifier(self::SECRET));

        $this->expectException(InvalidRefreshTokenException::class);
        $useCase->execute($raw);
    }

    public function test_deactivated_user_cannot_refresh(): void
    {
        $users = new InMemoryUserRepository();
        $userId = $users->save($this->user(organizationId: 1, status: 'disabled'));
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $issuer = new RefreshTokenIssuer($refreshTokens);
        $issued = $issuer->issue($userId, 1);
        $useCase = new RefreshSessionUseCase($refreshTokens, $users, $issuer, new LocalBearerTokenVerifier(self::SECRET));

        $this->expectException(InvalidRefreshTokenException::class);
        $useCase->execute($issued->rawToken);
    }

    public function test_tenant_mismatch_since_issuance_fails_closed(): void
    {
        $users = new InMemoryUserRepository();
        // User now lives in org 9, but the token was minted for org 1.
        $userId = $users->save($this->user(organizationId: 9));
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $issuer = new RefreshTokenIssuer($refreshTokens);
        $issued = $issuer->issue($userId, 1);
        $useCase = new RefreshSessionUseCase($refreshTokens, $users, $issuer, new LocalBearerTokenVerifier(self::SECRET));

        $this->expectException(InvalidRefreshTokenException::class);
        $useCase->execute($issued->rawToken);
    }

    private function user(int $organizationId, string $status = 'active'): User
    {
        return new User(
            email: 'op@example.com',
            passwordHash: password_hash('x', PASSWORD_DEFAULT),
            role: Role::Admin,
            organizationId: $organizationId,
            status: $status,
        );
    }
}
