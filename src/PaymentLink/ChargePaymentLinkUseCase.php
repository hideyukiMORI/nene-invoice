<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\Gateway\GatewayChargeRequest;
use NeneInvoice\Payment\Gateway\PaymentGatewayInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCaseInterface;

/**
 * Public, token-authenticated card payment against a payment link (ADR 0012/0013).
 *
 * Runs without an authenticated session: the unguessable link token is the trust
 * anchor, so the org is scoped *from the resolved link* (mirroring the public
 * download-token route). The invoice's outstanding balance is charged via the
 * gateway, then recorded as a `payment` (reusing {@see RecordPaymentUseCaseInterface},
 * keyed by the gateway charge id so a later webhook de-duplicates), and the link
 * is marked paid. No PAN passes through here (SAQ-A).
 */
final readonly class ChargePaymentLinkUseCase implements ChargePaymentLinkUseCaseInterface
{
    private const CURRENCY = 'jpy';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private PaymentLinkRepositoryInterface $links,
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private PaymentGatewayInterface $gateway,
        private RecordPaymentUseCaseInterface $recordPayment,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(string $rawToken, string $cardToken): ChargePaymentLinkResult
    {
        $now  = $this->clock->now()->format('Y-m-d H:i:s');
        $link = $this->links->findByHash(SecureTokenHelper::hash($rawToken));

        if ($link === null || $link->id === null || !$link->isPayable($now)) {
            throw new PaymentLinkNotPayableException('Payment link is not payable.');
        }

        // Public route (no OrgResolver): scope the org from the resolved link.
        $this->orgId->set($link->organizationId);

        $invoice = $this->invoices->findById($link->invoiceId);
        if ($invoice === null || $invoice->id === null
            || !in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
            throw new PaymentLinkNotPayableException('Invoice is not in a payable state.');
        }

        $outstanding = $invoice->totalCents - $this->payments->totalPaidForInvoice($invoice->id);
        if ($outstanding <= 0) {
            throw new PaymentLinkNotPayableException('Invoice has no outstanding balance.');
        }

        // Charge first (may throw PaymentGatewayException on decline), then record.
        $charge = $this->gateway->createCharge(new GatewayChargeRequest(
            amountCents: $outstanding,
            currency: self::CURRENCY,
            cardToken: $cardToken,
            metadata: [
                'payment_link_id' => (string) $link->id,
                'invoice_id'      => (string) $invoice->id,
            ],
        ));

        // Idempotency key = gateway charge id, shared with the #431 webhook so the
        // two settlement producers never double-book.
        $result = $this->recordPayment->execute(null, $invoice->id, new RecordPaymentInput(
            amountCents: $charge->amountCents,
            paidAt: $now,
            method: 'card',
            externalReference: $charge->id,
            idempotencyKey: $charge->id,
        ));

        $this->links->markPaid($link->id, $charge->id, $now);

        return new ChargePaymentLinkResult(
            paymentLinkId: $link->id,
            invoiceId: $invoice->id,
            chargeId: $charge->id,
            amountCents: $charge->amountCents,
            invoiceStatus: $result->invoice->status->value,
        );
    }
}
