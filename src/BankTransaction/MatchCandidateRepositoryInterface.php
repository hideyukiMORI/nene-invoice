<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Reads the open receivables a bank deposit could settle (#505): issued /
 * partially-paid invoices with a positive outstanding balance, joined with the
 * buyer's name for matching. Org-scoped like every other repository (ADR 0006).
 *
 * Read-only — it never records a payment; posting is a separate, operator-confirmed,
 * compliance-reviewed step (accounting-compliance.md).
 */
interface MatchCandidateRepositoryInterface
{
    /** @return list<OpenReceivable> open receivables with outstanding > 0, invoice id ascending */
    public function findOpenReceivables(): array;
}
