<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use DateTimeImmutable;

/**
 * How often a {@see RecurringInvoice} schedule generates a new invoice
 * (#503). String values are registered in `docs/explanation/terminology.md` §2.
 */
enum RecurringFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    private function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
        };
    }

    /**
     * The next run date after `$from` (a `Y-m-d` calendar date), advancing by the
     * frequency and **clamping the day to the target month's length** so the 31st
     * lands on the last day of a shorter month (e.g. 2026-01-31 monthly → 2026-02-28).
     *
     * Known limitation: a schedule anchored on the 29–31 then continues on the
     * clamped day after a short month rather than restoring the original day —
     * a dedicated anchor-day column is a follow-up (#503).
     */
    public function nextRunDate(string $from): string
    {
        $date = new DateTimeImmutable($from);
        $day  = (int) $date->format('j');

        $firstOfTarget = $date->modify('first day of this month')->modify('+' . $this->months() . ' months');
        $targetDay     = min($day, (int) $firstOfTarget->format('t'));

        return $firstOfTarget
            ->setDate((int) $firstOfTarget->format('Y'), (int) $firstOfTarget->format('n'), $targetDay)
            ->format('Y-m-d');
    }
}
