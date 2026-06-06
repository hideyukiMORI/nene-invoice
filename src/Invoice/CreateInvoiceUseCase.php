<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Closure;
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
 * Creates a draft invoice directly (without an originating quote): validates the
 * client and lines, computes totals (TaxCalculator, ADR 0004), then persists the
 * header and line items **atomically** (one transaction) and records an audit entry.
 *
 * No number is allocated here — drafts have no `invoice_number`; numbering happens
 * at issue time ({@see IssueInvoiceUseCase}), so a draft can be edited freely. The
 * header + line writes run inside the transaction manager, so the repositories are
 * rebuilt from the transaction-bound executor via the injected factories.
 */
final readonly class CreateInvoiceUseCase implements CreateInvoiceUseCaseInterface
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): InvoiceRepositoryInterface $invoicesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $invoicesFactory,
        private Closure $lineItemsFactory,
        private ClientRepositoryInterface $clients,
        private TaxCalculator $taxCalculator,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws InvoiceValidationException */
    public function execute(?int $actorUserId, CreateInvoiceInput $input): InvoiceWithLines
    {
        $organizationId = $this->orgId->get();

        // The client repo is org-scoped, so a cross-org client surfaces as null.
        $client = $this->clients->findById($input->clientId);

        if ($client === null) {
            throw new InvoiceValidationException('The selected client does not exist in your organization.');
        }

        if ($input->lines === []) {
            throw new InvoiceValidationException('An invoice requires at least one line item.');
        }

        foreach ($input->lines as $line) {
            if (!in_array($line->taxRateBps, self::ALLOWED_TAX_RATES_BPS, true)) {
                throw new InvoiceValidationException(sprintf('Tax rate %d bps is not allowed (use 1000 or 800).', $line->taxRateBps));
            }

            if ($line->quantity <= 0) {
                throw new InvoiceValidationException('Line item quantity must be greater than zero.');
            }

            if ($line->unitPriceCents < 0) {
                throw new InvoiceValidationException('Line item unit price must not be negative.');
            }
        }

        $totals = $this->taxCalculator->calculate($input->lines);

        $result = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $organizationId,
            $input,
            $totals,
        ): InvoiceWithLines {
            $invoices  = ($this->invoicesFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $invoiceId = $invoices->save(new Invoice(
                organizationId: $organizationId,
                clientId: $input->clientId,
                status: InvoiceStatus::Draft,
                subtotalCents: $totals->subtotalCents,
                taxCents: $totals->taxCents,
                totalCents: $totals->totalCents,
                notes: $input->notes,
            ));

            $lineEntities = [];
            foreach ($input->lines as $index => $line) {
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
                throw new LogicException('Invoice disappeared immediately after creation.');
            }

            return new InvoiceWithLines($saved, $lineItems->findByParent(LineItemParent::Invoice, $invoiceId));
        });

        $this->audit->record($actorUserId, $organizationId, 'invoice.created', 'invoice', $result->invoice->id, null, InvoiceResponse::toArray($result->invoice, $result->lines));

        return $result;
    }
}
