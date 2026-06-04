<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use DateTimeImmutable;

/**
 * Japanese closing-date + payment-site (締め日 ＋ 支払サイト) model for invoice
 * payment due dates (Issue #268, design B).
 *
 * - `closingDay` 1–31, or null for 末日 (month-end) closing.
 * - `monthOffset` 0 = 当月, 1 = 翌月, 2 = 翌々月 … months after the closing month.
 * - `payDay` 1–31, or null for 末日 (month-end) payment.
 *
 * Example: 月末締め翌月末払い → (null, 1, null). 20日締め翌月10日払い → (20, 1, 10).
 *
 * The number of days itself is a business/contract choice; for 下請 / フリーランス
 * relationships the law caps it at 60 days from 受領 — see accounting-compliance.md.
 */
final readonly class PaymentTerms
{
    public function __construct(
        public ?int $closingDay,
        public int $monthOffset,
        public ?int $payDay,
    ) {
    }

    /**
     * Computes the payment due date for an invoice issued on $issueDate.
     * Returns a `Y-m-d` string. Month-end days are clamped per target month.
     */
    public function dueDateFrom(DateTimeImmutable $issueDate): string
    {
        $issueDay = (int) $issueDate->format('j');
        $issueLastDom = (int) $issueDate->format('t');
        $monthStart = $issueDate->modify('first day of this month')->setTime(0, 0, 0);

        // Closing day within the issue month (末日 when null), clamped to length.
        $closingDom = min($this->closingDay ?? $issueLastDom, $issueLastDom);

        // An invoice dated after the closing day rolls into the next cycle.
        $closingMonth = $issueDay > $closingDom ? $monthStart->modify('+1 month') : $monthStart;

        $payMonth = $closingMonth->modify('+' . $this->monthOffset . ' month');
        $payLastDom = (int) $payMonth->format('t');
        $payDom = min($this->payDay ?? $payLastDom, $payLastDom);

        return $payMonth->modify('+' . ($payDom - 1) . ' day')->format('Y-m-d');
    }
}
