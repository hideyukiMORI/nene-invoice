<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Closure;
use DateTimeImmutable;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;

/**
 * Edits a recurring schedule: re-validates the client and lines, recomputes
 * totals (ADR 0004), replaces the line template, and persists the header +
 * lines atomically with an audit entry (before/after).
 */
final readonly class UpdateRecurringInvoiceUseCase
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): RecurringInvoiceRepositoryInterface $recurringFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
        private LineItemRepositoryInterface $lineItems,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $recurringFactory,
        private Closure $lineItemsFactory,
        private ClientRepositoryInterface $clients,
        private TaxCalculator $taxCalculator,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws RecurringInvoiceNotFoundException
     * @throws RecurringInvoiceValidationException
     */
    public function execute(?int $actorUserId, int $id, UpdateRecurringInvoiceInput $input): RecurringInvoiceWithLines
    {
        $organizationId = $this->orgId->get();

        $existing = $this->recurring->findById($id);
        if ($existing === null) {
            throw new RecurringInvoiceNotFoundException($id);
        }

        if (trim($input->name) === '') {
            throw new RecurringInvoiceValidationException('A recurring invoice requires a name.');
        }

        if ($this->clients->findById($input->clientId) === null) {
            throw new RecurringInvoiceValidationException('The selected client does not exist in your organization.');
        }

        if (!self::isValidDate($input->nextRunOn)) {
            throw new RecurringInvoiceValidationException('The next run date must be a valid date (YYYY-MM-DD).');
        }

        if ($input->lines === []) {
            throw new RecurringInvoiceValidationException('A recurring invoice requires at least one line item.');
        }

        foreach ($input->lines as $line) {
            if (!in_array($line->taxRateBps, self::ALLOWED_TAX_RATES_BPS, true)) {
                throw new RecurringInvoiceValidationException(sprintf('Tax rate %d bps is not allowed (use 1000 or 800).', $line->taxRateBps));
            }
            if ($line->quantity <= 0) {
                throw new RecurringInvoiceValidationException('Line item quantity must be greater than zero.');
            }
            if ($line->unitPriceCents < 0) {
                throw new RecurringInvoiceValidationException('Line item unit price must not be negative.');
            }
        }

        $before = RecurringInvoiceResponse::toArray($existing, $this->lineItems->findByParent(LineItemParent::RecurringInvoice, $id));
        $totals = $this->taxCalculator->calculate($input->lines);

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $id,
            $input,
            $existing,
            $totals,
            $before,
        ): RecurringInvoiceWithLines {
            $recurring = ($this->recurringFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $recurring->update(new RecurringInvoice(
                organizationId: $existing->organizationId,
                clientId: $input->clientId,
                name: $input->name,
                frequency: $input->frequency,
                subtotalCents: $totals->subtotalCents,
                taxCents: $totals->taxCents,
                totalCents: $totals->totalCents,
                nextRunOn: $input->nextRunOn,
                lastRunOn: $existing->lastRunOn,
                isActive: $input->isActive,
                notes: $input->notes,
                id: $id,
                createdAt: $existing->createdAt,
                updatedAt: $existing->updatedAt,
            ));

            $entities = [];
            foreach ($input->lines as $index => $line) {
                $entities[] = new LineItem(
                    parentType: LineItemParent::RecurringInvoice,
                    parentId: $id,
                    description: $line->description,
                    quantity: $line->quantity,
                    unitPriceCents: $line->unitPriceCents,
                    taxRateBps: $line->taxRateBps,
                    sortOrder: $index,
                );
            }
            $lineItems->replaceForParent(LineItemParent::RecurringInvoice, $id, $entities);

            $saved = $recurring->findById($id);
            if ($saved === null) {
                throw new LogicException('Recurring invoice disappeared immediately after update.');
            }

            $result = new RecurringInvoiceWithLines($saved, $lineItems->findByParent(LineItemParent::RecurringInvoice, $id));

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'recurring_invoice.updated',
                entityType: 'recurring_invoice',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $before,
                after: RecurringInvoiceResponse::toArray($result->schedule, $result->lines),
            ));

            return $result;
        });
    }

    private static function isValidDate(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}
