<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Payment\Gateway\GatewayCharge;
use NeneInvoice\Payment\Gateway\GatewayChargeRequest;
use NeneInvoice\Payment\Gateway\PaymentGatewayException;
use NeneInvoice\Payment\Gateway\PaymentGatewayInterface;

/**
 * Configurable {@see PaymentGatewayInterface} double. Returns a charge whose id
 * is `$chargeId` and amount echoes the request, or throws when `$declineMessage`
 * is set. Records the last request for assertions (e.g. metadata).
 */
final class FakeGateway implements PaymentGatewayInterface
{
    public ?GatewayChargeRequest $lastRequest = null;

    public function __construct(
        private readonly string $chargeId = 'ch_fake_1',
        private readonly ?string $declineMessage = null,
        private readonly bool $connectivity = true,
        private readonly bool $connectivityThrows = false,
    ) {
    }

    public function name(): string
    {
        return 'fake';
    }

    public function verifyConnectivity(): bool
    {
        if ($this->connectivityThrows) {
            throw new PaymentGatewayException('unreachable');
        }

        return $this->connectivity;
    }

    public function createCharge(GatewayChargeRequest $request): GatewayCharge
    {
        $this->lastRequest = $request;

        if ($this->declineMessage !== null) {
            throw new PaymentGatewayException($this->declineMessage);
        }

        return new GatewayCharge(
            id: $this->chargeId,
            paid: true,
            amountCents: $request->amountCents,
            currency: $request->currency,
        );
    }
}
