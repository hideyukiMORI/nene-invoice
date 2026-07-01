<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use NeneInvoice\Payment\RecordPaymentInput;
use NeneInvoice\Payment\RecordPaymentUseCaseInterface;

/**
 * Confirms an operator's match of a staged bank deposit to an invoice and records
 * the payment (#505, increment ⑤ — the first step that carries accounting weight).
 *
 * It does **not** implement any bookkeeping of its own: it reuses the
 * tax-signed-off {@see RecordPaymentUseCaseInterface}, which is idempotent, guards
 * over-payment, and writes the audit entry. The **exact** deposit amount is
 * recorded — a deposit short of the balance leaves the remainder outstanding
 * (a fee/short-payment write-off is a separate, tax-gated step, deliberately not
 * done here — accounting-compliance.md). On success the line advances to `posted`
 * and the remitter name is learned as a {@see PayerAlias} so future deposits from
 * the same payer match automatically.
 *
 * Idempotent: the payment carries a deterministic idempotency key derived from the
 * bank line, so a retried confirm returns the existing payment rather than
 * double-posting; if a prior confirm recorded the payment but the status update did
 * not land, re-confirming the same invoice reconciles the line without a duplicate.
 */
final readonly class ConfirmMatchUseCase
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private PayerAliasRepositoryInterface $aliases,
        private RecordPaymentUseCaseInterface $recordPayment,
    ) {
    }

    /**
     * @throws BankTransactionNotFoundException
     * @throws BankTransactionValidationException
     * @throws \NeneInvoice\Invoice\InvoiceNotFoundException
     * @throws \NeneInvoice\Payment\PaymentValidationException
     * @throws \NeneInvoice\Payment\PaymentExceedsOutstandingException
     */
    public function execute(?int $actorUserId, int $bankTransactionId, int $invoiceId): ConfirmMatchResult
    {
        $transaction = $this->transactions->findById($bankTransactionId);

        if ($transaction === null) {
            throw new BankTransactionNotFoundException($bankTransactionId);
        }

        if ($transaction->direction !== BankTransactionDirection::Credit) {
            throw new BankTransactionValidationException('Only credit (deposit) bank lines can be reconciled to an invoice.');
        }

        if ($transaction->status === BankTransactionStatus::Ignored) {
            throw new BankTransactionValidationException('This bank line was ignored and cannot be reconciled.');
        }

        if ($transaction->status === BankTransactionStatus::Posted && $transaction->matchedInvoiceId !== $invoiceId) {
            throw new BankTransactionValidationException('This bank line is already reconciled to a different invoice.');
        }

        // Reuse the tax-signed-off payment path. Idempotent on the bank line, so a
        // retry (or a recovered half-applied confirm) never double-posts.
        $result = $this->recordPayment->execute(
            $actorUserId,
            $invoiceId,
            new RecordPaymentInput(
                amountCents: $transaction->amountCents,
                paidAt: $transaction->valueDate,
                method: 'bank_transfer',
                externalReference: $transaction->bankReference ?? $this->idempotencyKey($bankTransactionId),
                idempotencyKey: $this->idempotencyKey($bankTransactionId),
            ),
        );

        // Already posted to this same invoice: nothing to advance or learn again.
        if ($transaction->status === BankTransactionStatus::Posted) {
            return new ConfirmMatchResult($transaction, $result);
        }

        $posted = new BankTransaction(
            organizationId: $transaction->organizationId,
            valueDate: $transaction->valueDate,
            direction: $transaction->direction,
            amountCents: $transaction->amountCents,
            payerName: $transaction->payerName,
            description: $transaction->description,
            bankReference: $transaction->bankReference,
            status: BankTransactionStatus::Posted,
            matchedInvoiceId: $invoiceId,
            matchedPaymentId: $result->payment->id,
            importedAt: $transaction->importedAt,
            id: $transaction->id,
            createdAt: $transaction->createdAt,
            updatedAt: $transaction->updatedAt,
        );
        $this->transactions->update($posted);

        $this->learnAlias($transaction->organizationId, $transaction->payerName, $result->invoice->clientId);

        return new ConfirmMatchResult($posted, $result);
    }

    private function idempotencyKey(int $bankTransactionId): string
    {
        return sprintf('bank-txn-%d', $bankTransactionId);
    }

    private function learnAlias(int $organizationId, ?string $payerName, int $clientId): void
    {
        if ($payerName === null) {
            return;
        }

        $normalized = PayerNameNormalizer::normalize($payerName);
        if ($normalized === '') {
            return;
        }

        // The repository re-forces the org from the request-scoped holder (ADR 0006);
        // the id here just mirrors the source line's organization.
        $this->aliases->upsert(new PayerAlias(
            organizationId: $organizationId,
            normalizedName: $normalized,
            clientId: $clientId,
        ));
    }
}
