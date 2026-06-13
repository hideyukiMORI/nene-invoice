<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use NeneInvoice\Payment\Gateway\PaymentGatewayException;

interface ChargePaymentLinkUseCaseInterface
{
    /**
     * Charges the invoice behind a payment link using a gateway card token.
     *
     * @throws PaymentLinkNotPayableException link missing/expired/revoked/paid or nothing outstanding
     * @throws PaymentGatewayException charge declined or gateway error
     */
    public function execute(string $rawToken, string $cardToken): ChargePaymentLinkResult;
}
