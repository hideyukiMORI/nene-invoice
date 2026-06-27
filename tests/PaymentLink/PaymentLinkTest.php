<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentLinkTest extends TestCase
{
    private function link(PaymentLinkStatus $status, string $expiresAt): PaymentLink
    {
        return new PaymentLink(
            organizationId: 1,
            invoiceId: 1,
            tokenHash: 'hash',
            gateway: 'payjp',
            status: $status,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Expiry is inclusive of the boundary: `isExpired` uses `<=`, so the exact
     * expiry instant is already expired.
     */
    #[DataProvider('expiryCases')]
    public function test_is_expired(string $now, bool $expected): void
    {
        self::assertSame($expected, $this->link(PaymentLinkStatus::Active, '2026-06-06 12:00:00')->isExpired($now));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function expiryCases(): iterable
    {
        yield 'one second before -> not expired' => ['2026-06-06 11:59:59', false];
        yield 'exactly at expiry -> expired'     => ['2026-06-06 12:00:00', true];
        yield 'one second after -> expired'      => ['2026-06-06 12:00:01', true];
    }

    /**
     * Payable requires Active status AND not-yet-expired; the expiry boundary
     * and any non-Active status both make it unpayable.
     */
    #[DataProvider('payableCases')]
    public function test_is_payable(PaymentLinkStatus $status, string $now, bool $expected): void
    {
        self::assertSame($expected, $this->link($status, '2026-06-06 12:00:00')->isPayable($now));
    }

    /** @return iterable<string, array{PaymentLinkStatus, string, bool}> */
    public static function payableCases(): iterable
    {
        yield 'active before expiry'  => [PaymentLinkStatus::Active, '2026-06-06 11:59:59', true];
        yield 'active at expiry'      => [PaymentLinkStatus::Active, '2026-06-06 12:00:00', false];
        yield 'active after expiry'   => [PaymentLinkStatus::Active, '2026-06-06 12:00:01', false];
        yield 'paid before expiry'    => [PaymentLinkStatus::Paid, '2026-06-06 11:59:59', false];
        yield 'revoked before expiry' => [PaymentLinkStatus::Revoked, '2026-06-06 11:59:59', false];
    }
}
