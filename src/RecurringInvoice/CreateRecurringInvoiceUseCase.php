<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Closure;
use DateTimeImmutable;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;

/**
 * Creates a recurring-billing schedule: validates the client and lines, computes
 * totals (TaxCalculator, ADR 0004), then persists the header + line template
 * (`parent_type=recurring_invoice`) atomically and records an audit entry.
 *
 * This only stores the schedule. Generating draft invoices is
 * {@see GenerateDueRecurringInvoicesUseCase}; issuing is a separate, tax-reviewed step.
 */
final readonly class CreateRecurringInvoiceUseCase
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): RecurringInvoiceRepositoryInterface $recurringFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $recurringFactory,
        private Closure $lineItemsFactory,
        private ClientRepositoryInterface $clients,
        private TaxCalculator $taxCalculator,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws RecurringInvoiceValidationException */
    public function execute(?int $actorUserId, CreateRecurringInvoiceInput $input): RecurringInvoiceWithLines
    {
        $organizationId = $this->orgId->get();

        if (trim($input->name) === '') {
            throw new RecurringInvoiceValidationException('A recurring invoice requires a name.');
        }

        if ($this->clients->findById($input->clientId) === null) {
            throw new RecurringInvoiceValidationException('The selected client does not exist in your organization.');
        }

        if (!self::isValidDate($input->firstRunOn)) {
            throw new RecurringInvoiceValidationException('The first run date must be a valid date (YYYY-MM-DD).');
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

        $totals = $this->taxCalculator->calculate($input->lines);

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $input,
            $totals,
        ): RecurringInvoiceWithLines {
            $recurring = ($this->recurringFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $id = $recurring->save(new RecurringInvoice(
                organizationId: $organizationId,
                clientId: $input->clientId,
                name: $input->name,
                frequency: $input->frequency,
                subtotalCents: $totals->subtotalCents,
                taxCents: $totals->taxCents,
                totalCents: $totals->totalCents,
                nextRunOn: $input->firstRunOn,
                isActive: $input->isActive,
                notes: $input->notes,
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
                throw new LogicException('Recurring invoice disappeared immediately after creation.');
            }

            $result = new RecurringInvoiceWithLines($saved, $lineItems->findByParent(LineItemParent::RecurringInvoice, $id));

            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'recurring_invoice.created', 'recurring_invoice', $id, null, RecurringInvoiceResponse::toArray($result->schedule, $result->lines));

            return $result;
        });
    }

    private static function isValidDate(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}
