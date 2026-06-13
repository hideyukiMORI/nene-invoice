<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Integration;

use NeneInvoice\Payment\Gateway\GatewayChargeRequest;
use NeneInvoice\Payment\Gateway\PayjpGateway;
use NeneInvoice\Payment\Gateway\PaymentGatewayException;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real PAY.JP API with the configured **test** secret key. Skipped
 * unless `PAYJP_SECRET_KEY` (sk_test_…) is present, so CI without the key and
 * contributors without an account skip it. Test mode moves no real money.
 *
 * Scope: authentication + error parsing against the live API. The *successful*
 * charge path cannot be exercised here because PAY.JP deliberately rejects raw
 * card numbers sent server-side (`unsafe_credit_card_param`) — card tokens must
 * be minted in the browser via PAY.JP Checkout / payjp.js. That is exactly our
 * SAQ-A design (no PAN server-side); the happy path is covered by
 * {@see \NeneInvoice\Tests\PaymentLink\ChargePaymentLinkUseCaseTest} with a fake
 * gateway, and end-to-end via a real Checkout token (manual / browser E2E).
 */
final class PayjpGatewayChargeTest extends TestCase
{
    private string $secretKey = '';

    protected function setUp(): void
    {
        $key = getenv('PAYJP_SECRET_KEY') ?: ($_ENV['PAYJP_SECRET_KEY'] ?? '');
        if (!is_string($key) || !str_starts_with($key, 'sk_test_')) {
            self::markTestSkipped('PAYJP_SECRET_KEY (sk_test_…) not set; skipping live PAY.JP test.');
        }
        $this->secretKey = $key;
    }

    public function test_authenticates_and_parses_a_declined_charge(): void
    {
        // A bad token reaches PAY.JP (auth OK) and comes back as a structured
        // error, which the adapter surfaces as a PaymentGatewayException.
        $gateway = new PayjpGateway($this->secretKey);

        $this->expectException(PaymentGatewayException::class);
        $gateway->createCharge(new GatewayChargeRequest(
            amountCents: 500,
            currency: 'jpy',
            cardToken: 'tok_obviously_invalid',
        ));
    }

    public function test_missing_key_fails_closed_without_network(): void
    {
        $gateway = new PayjpGateway('');

        $this->expectException(PaymentGatewayException::class);
        $gateway->createCharge(new GatewayChargeRequest(
            amountCents: 500,
            currency: 'jpy',
            cardToken: 'tok_whatever',
        ));
    }
}
