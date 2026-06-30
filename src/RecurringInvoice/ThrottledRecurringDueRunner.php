<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Support\Jst;

/**
 * Throttled inline runner (Tier A): generates due recurring drafts for the
 * organization resolved on the current request, at most once per org per JST
 * day. Issuing/numbering stays a separate, human, tax-reviewed step
 * (docs/explanation/accounting-compliance.md §5) — this only generates drafts.
 */
final readonly class ThrottledRecurringDueRunner implements RecurringDueRunnerInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private GenerateDueRecurringInvoicesUseCase $useCase,
        private RecurringRunThrottleInterface $throttle,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function runForCurrentOrg(): ?GenerateDueRecurringInvoicesResult
    {
        if (!$this->orgId->isSet()) {
            return null;
        }

        $organizationId = $this->orgId->get();
        $today          = Jst::of($this->clock->now())->format('Y-m-d');

        if (!$this->throttle->claim($organizationId, $today)) {
            return null;
        }

        // System actor (null): auto-generated drafts carry a null actor in the
        // audit trail.
        return $this->useCase->execute(null);
    }
}
