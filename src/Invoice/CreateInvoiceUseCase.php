<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;

/**
 * Creates a draft invoice directly (without an originating quote): validates the
 * client and lines, computes totals (TaxCalculator, ADR 0004), persists the
 * header and line items, and records an audit entry.
 *
 * No number is allocated here — drafts have no `invoice_number`; numbering happens
 * at issue time ({@see IssueInvoiceUseCase}), so a draft can be edited freely.
 */
final readonly class CreateInvoiceUseCase
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private ClientRepositoryInterface $clients,
        private TaxCalculator $taxCalculator,
        private AuditRecorderInterface $audit,
    ) {
    }

    /** @throws InvoiceValidationException */
    public function execute(int $organizationId, ?int $actorUserId, CreateInvoiceInput $input): InvoiceWithLines
    {
        $client = $this->clients->findById($input->clientId);

        if ($client === null || $client->organizationId !== $organizationId) {
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

        $invoiceId = $this->invoices->save(new Invoice(
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

        $this->lineItems->replaceForParent(LineItemParent::Invoice, $invoiceId, $lineEntities);

        $saved = $this->invoices->findById($invoiceId);

        if ($saved === null) {
            throw new LogicException('Invoice disappeared immediately after creation.');
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId);

        $this->audit->record($actorUserId, $organizationId, 'invoice.created', 'invoice', $invoiceId, null, InvoiceResponse::toArray($saved, $lines));

        return new InvoiceWithLines($saved, $lines);
    }
}
