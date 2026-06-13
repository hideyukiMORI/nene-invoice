<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Payment\PaymentExceedsOutstandingException;
use NeneInvoice\Payment\PaymentValidationException;
use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCaseInterface;

/**
 * Records a confirmed gateway settlement (e.g. PAY.JP `charge.succeeded`) against
 * its payment link. This is the **backstop** producer to the synchronous charge
 * (#439): both key the payment by the gateway **charge id**, so a webhook replay
 * (PAY.JP retries up to 3×) or a duplicate of the synchronous payment is
 * de-duplicated by {@see RecordPaymentUseCaseInterface}'s idempotency.
 *
 * Runs without an authenticated session: the org is recovered *from the resolved
 * link* (the event was gateway-authenticated upstream), mirroring the public
 * payment route. No card data is present (SAQ-A).
 */
final readonly class RecordSettlementUseCase implements RecordSettlementUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private PaymentLinkRepositoryInterface $links,
        private RecordPaymentUseCaseInterface $recordPayment,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $paymentLinkId, string $chargeId, int $amountCents): SettlementOutcome
    {
        $link = $paymentLinkId !== null
            ? $this->links->findByIdUnscoped($paymentLinkId)
            : $this->links->findByGatewaySessionId($chargeId);

        if ($link === null || $link->id === null) {
            return SettlementOutcome::LinkNotFound;
        }

        // Recover the tenant from the resolved link (no OrgResolver on webhooks).
        $this->orgId->set($link->organizationId);

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        try {
            $this->recordPayment->execute(null, $link->invoiceId, new RecordPaymentInput(
                amountCents: $amountCents,
                paidAt: $now,
                method: 'card',
                externalReference: $chargeId,
                idempotencyKey: $chargeId,
            ));
        } catch (InvoiceNotFoundException | PaymentValidationException | PaymentExceedsOutstandingException) {
            // Invoice gone, not issued, or already covered by another payment —
            // nothing safe to record. Acknowledge so the gateway stops retrying.
            return SettlementOutcome::Ignored;
        }

        // Idempotent: only an active link transitions; a replay is a no-op.
        $this->links->markPaid($link->id, $chargeId, $now);

        return SettlementOutcome::Recorded;
    }
}
