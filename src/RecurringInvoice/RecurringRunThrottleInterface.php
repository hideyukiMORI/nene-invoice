<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * Per-organization, once-per-day guard for the inline (Tier A) recurring run.
 *
 * Shared hosting cannot run cron, so the due check piggybacks on authenticated
 * admin requests ({@see RecurringDueCheckMiddleware}). This throttle keeps that
 * check to at most once per organization per JST calendar day, so the cost on a
 * normal request is a single marker read.
 */
interface RecurringRunThrottleInterface
{
    /**
     * Claim the run slot for an organization on the given JST date (`Y-m-d`).
     *
     * @return bool true if the caller claimed the slot (the run should proceed);
     *              false if this org already ran (or claimed) on `$date`
     */
    public function claim(int $organizationId, string $date): bool;
}
