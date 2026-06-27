<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceDownloadTokenTest extends TestCase
{
    /**
     * Expiry is inclusive of the boundary (`expiresAt <= now`): the exact expiry
     * instant is already expired, one second earlier is still valid.
     */
    #[DataProvider('expiryCases')]
    public function test_is_expired(string $now, bool $expected): void
    {
        $token = new InvoiceDownloadToken(
            invoiceId: 1,
            organizationId: 1,
            tokenHash: 'hash',
            expiresAt: '2026-06-13 12:00:00',
            createdAt: '2026-06-06 12:00:00',
        );

        self::assertSame($expected, $token->isExpired($now));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function expiryCases(): iterable
    {
        yield 'one second before -> valid'   => ['2026-06-13 11:59:59', false];
        yield 'exactly at expiry -> expired' => ['2026-06-13 12:00:00', true];
        yield 'one second after -> expired'  => ['2026-06-13 12:00:01', true];
    }
}
