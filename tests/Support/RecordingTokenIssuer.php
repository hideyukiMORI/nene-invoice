<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Auth\TokenIssuerInterface;

/**
 * Test double for {@see TokenIssuerInterface} that captures the claims it was
 * asked to sign and returns a deterministic, inspectable token string.
 */
final class RecordingTokenIssuer implements TokenIssuerInterface
{
    /** @var array<string, mixed> claims passed to the most recent issue() call */
    public array $claims = [];

    public function issue(array $claims): string
    {
        $this->claims = $claims;

        return 'signed.' . (is_string($claims['jti'] ?? null) ? $claims['jti'] : '');
    }
}
