<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * Seam for the inline (Tier A) recurring execution: run the due check for the
 * organization resolved on the current request. Keeps
 * {@see RecurringDueCheckMiddleware} free of generation/throttle wiring and
 * unit-testable with a fake.
 */
interface RecurringDueRunnerInterface
{
    /**
     * Generate due drafts for the current request's organization, throttled to
     * once per org per JST day. Returns the result, or null when skipped (no org
     * resolved on the request, or already run today).
     */
    public function runForCurrentOrg(): ?GenerateDueRecurringInvoicesResult;
}
