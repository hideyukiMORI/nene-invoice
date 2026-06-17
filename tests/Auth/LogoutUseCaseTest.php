<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\LogoutUseCase;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Auth\RefreshTokenSecret;
use NeneInvoice\Tests\Support\InMemoryRefreshTokenRepository;
use PHPUnit\Framework\TestCase;

final class LogoutUseCaseTest extends TestCase
{
    public function test_revokes_the_token_family(): void
    {
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $issued = (new RefreshTokenIssuer($refreshTokens))->issue(42, 1);

        (new LogoutUseCase($refreshTokens))->execute($issued->rawToken);

        $record = $refreshTokens->findByHash(RefreshTokenSecret::hash($issued->rawToken));
        self::assertNotNull($record);
        self::assertNotNull($record->revokedAt);
    }

    public function test_is_a_noop_for_a_missing_or_unknown_token(): void
    {
        $refreshTokens = new InMemoryRefreshTokenRepository();
        $useCase = new LogoutUseCase($refreshTokens);

        // No exception for null/empty/unknown — logout is idempotent.
        $useCase->execute(null);
        $useCase->execute('');
        $useCase->execute('unknown');

        $this->addToAssertionCount(1);
    }
}
