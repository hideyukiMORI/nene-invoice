<?php

declare(strict_types=1);

namespace NeneInvoice\GatewaySettings;

use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Payment\Gateway\PaymentGatewayException;
use NeneInvoice\Payment\Gateway\PaymentGatewayInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/gateway-settings/test` — runs a live connectivity check against
 * the gateway using the configured (env) credentials. Never returns the secret.
 * Capability: ManageCompanySettings (via CapabilityResolver).
 *
 * Always 200 with an `ok` flag + `detail` reason (`connected` / `not_configured`
 * / `invalid_credentials` / `unreachable`) so the UI can render the outcome via
 * the inline-alert error type rather than an HTTP error.
 */
final readonly class TestGatewayConnectivityHandler implements RequestHandlerInterface
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private JsonResponseFactory $json,
        private bool $secretSet,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->secretSet) {
            return $this->result(false, 'not_configured');
        }

        try {
            $ok = $this->gateway->verifyConnectivity();
        } catch (PaymentGatewayException) {
            return $this->result(false, 'unreachable');
        }

        return $this->result($ok, $ok ? 'connected' : 'invalid_credentials');
    }

    private function result(bool $ok, string $detail): ResponseInterface
    {
        return $this->json->create(['ok' => $ok, 'detail' => $detail], 200);
    }
}
