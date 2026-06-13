<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

/**
 * Abstraction over a hosted card-payment gateway (ADR 0012/0013). The launch
 * implementation is PAY.JP; Stripe Checkout is the designated second adapter, so
 * this contract must not bake in any one gateway's specifics (e.g. webhook
 * signature scheme — PAY.JP uses a token header, not HMAC).
 *
 * Only hosted/tokenized flows are permitted: the raw PAN never reaches this
 * application (SAQ-A).
 */
interface PaymentGatewayInterface
{
    /** Registry value identifying the gateway (e.g. `payjp`). */
    public function name(): string;

    /**
     * Creates a card charge from a gateway-issued card token.
     *
     * @throws PaymentGatewayException on decline, gateway error, or transport failure
     */
    public function createCharge(GatewayChargeRequest $request): GatewayCharge;
}
