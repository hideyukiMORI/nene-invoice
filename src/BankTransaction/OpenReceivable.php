<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * An unpaid invoice a bank deposit might settle (#505), as read from the ledger
 * for reconciliation. `outstandingCents` is the still-owed balance
 * (`total_cents` minus non-void payments); `clientName` is the buyer's
 * human-readable name and `clientNameKana` its kana reading (used by the matcher
 * for remitter comparison, as bank lines carry kana).
 *
 * This is a read projection for matching, not a billing record — no accounting
 * weight until an operator confirms and a payment is recorded.
 */
final readonly class OpenReceivable
{
    public function __construct(
        public int $invoiceId,
        public int $clientId,
        public int $outstandingCents,
        public ?string $invoiceNumber = null,
        public ?string $clientName = null,
        public ?string $clientNameKana = null,
    ) {
    }

    /**
     * The name the matcher scores against: the kana reading when present (bank
     * remitter names are kana), otherwise the display name.
     */
    public function matchName(): ?string
    {
        return $this->clientNameKana ?? $this->clientName;
    }
}
