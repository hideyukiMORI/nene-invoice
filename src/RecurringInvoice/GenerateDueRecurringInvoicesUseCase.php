<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceResponse;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemInput;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Support\Jst;

/**
 * Generates **draft** invoices from recurring schedules that are due (#503).
 *
 * Each due schedule's line template (stored under `parent_type=recurring_invoice`)
 * is copied into a fresh draft invoice — the same draft-creation path as
 * {@see \NeneInvoice\Invoice\CreateInvoiceUseCase}, so totals are recomputed by
 * the tax calculator (ADR 0004). Drafts carry no number and no qualified-invoice
 * lock, so this step is compliance-light; **issuing/numbering is deliberately out
 * of scope** (a later, tax-reviewed step). After generating, the schedule's
 * `next_run_on` advances by its frequency and `last_run_on` is stamped.
 *
 * Idempotency: a schedule already run on the same date is skipped, and because
 * `next_run_on` advances past `today` for the common (non-overdue) case, a second
 * run on the same day produces nothing — so it never double-bills.
 */
final readonly class GenerateDueRecurringInvoicesUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): RecurringInvoiceRepositoryInterface $recurringFactory
     * @param Closure(DatabaseQueryExecutorInterface): InvoiceRepositoryInterface $invoicesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
        private LineItemRepositoryInterface $lineItems,
        private ClientRepositoryInterface $clients,
        private TaxCalculator $taxCalculator,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $recurringFactory,
        private Closure $invoicesFactory,
        private Closure $lineItemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId): GenerateDueRecurringInvoicesResult
    {
        $organizationId = $this->orgId->get();
        $runDate        = Jst::of($this->clock->now())->format('Y-m-d');

        $generated = [];

        foreach ($this->recurring->findDue($runDate) as $schedule) {
            $scheduleId = $schedule->id;
            if ($scheduleId === null) {
                continue;
            }

            // Already generated today (defensive against a same-day re-run).
            if ($schedule->lastRunOn === $runDate) {
                continue;
            }

            $template = $this->lineItems->findByParent(LineItemParent::RecurringInvoice, $scheduleId);
            if ($template === []) {
                continue; // nothing to bill — leave the schedule untouched
            }

            // Skip a schedule whose client was removed (or is cross-org): no invoice.
            if ($this->clients->findById($schedule->clientId) === null) {
                continue;
            }

            $inputs = array_map(
                static fn (LineItem $line): LineItemInput => new LineItemInput(
                    $line->description,
                    $line->quantity,
                    $line->unitPriceCents,
                    $line->taxRateBps,
                ),
                $template,
            );

            $totals = $this->taxCalculator->calculate($inputs);

            $invoiceId = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
                $actorUserId,
                $organizationId,
                $schedule,
                $scheduleId,
                $template,
                $totals,
                $runDate,
            ): int {
                $invoices  = ($this->invoicesFactory)($exec);
                $lineItems = ($this->lineItemsFactory)($exec);
                $recurring = ($this->recurringFactory)($exec);

                $invoiceId = $invoices->save(new Invoice(
                    organizationId: $organizationId,
                    clientId: $schedule->clientId,
                    status: InvoiceStatus::Draft,
                    subtotalCents: $totals->subtotalCents,
                    taxCents: $totals->taxCents,
                    totalCents: $totals->totalCents,
                    notes: $schedule->notes,
                ));

                $lineEntities = [];
                foreach ($template as $index => $line) {
                    $lineEntities[] = new LineItem(
                        parentType: LineItemParent::Invoice,
                        parentId: $invoiceId,
                        description: $line->description,
                        quantity: $line->quantity,
                        unitPriceCents: $line->unitPriceCents,
                        taxRateBps: $line->taxRateBps,
                        sortOrder: $index,
                    );
                }
                $lineItems->replaceForParent(LineItemParent::Invoice, $invoiceId, $lineEntities);

                $saved = $invoices->findById($invoiceId);
                if ($saved === null) {
                    throw new LogicException('Invoice disappeared immediately after recurring generation.');
                }

                $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                    action: 'invoice.created',
                    entityType: 'invoice',
                    entityId: $invoiceId,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: null,
                    after: InvoiceResponse::toArray($saved, $lineItems->findByParent(LineItemParent::Invoice, $invoiceId)),
                ));

                // Advance the schedule anchored on its own next_run_on (no drift
                // toward the run date) and stamp the last run.
                $recurring->update(new RecurringInvoice(
                    organizationId: $schedule->organizationId,
                    clientId: $schedule->clientId,
                    name: $schedule->name,
                    frequency: $schedule->frequency,
                    subtotalCents: $schedule->subtotalCents,
                    taxCents: $schedule->taxCents,
                    totalCents: $schedule->totalCents,
                    nextRunOn: $schedule->frequency->nextRunDate($schedule->nextRunOn),
                    lastRunOn: $runDate,
                    isActive: $schedule->isActive,
                    notes: $schedule->notes,
                    id: $scheduleId,
                    createdAt: $schedule->createdAt,
                    updatedAt: $schedule->updatedAt,
                ));

                return $invoiceId;
            });

            $generated[] = ['recurring_invoice_id' => $scheduleId, 'invoice_id' => $invoiceId];
        }

        return new GenerateDueRecurringInvoicesResult($generated);
    }
}
