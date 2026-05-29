<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteRepositoryInterface;
use NeneInvoice\Quote\QuoteStatus;
use NeneInvoice\Quote\QuoteValidationException;

/**
 * Converts an accepted quote into a new draft invoice, copying the client,
 * totals, and line items. The invoice is numbered and validated later, at issue.
 */
final readonly class ConvertQuoteToInvoiceUseCase
{
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * @throws QuoteNotFoundException
     * @throws QuoteValidationException
     */
    public function execute(int $organizationId, ?int $actorUserId, int $quoteId): InvoiceWithLines
    {
        $quote = $this->quotes->findById($quoteId);

        if ($quote === null || $quote->organizationId !== $organizationId) {
            throw new QuoteNotFoundException($quoteId);
        }

        if ($quote->status !== QuoteStatus::Accepted) {
            throw new QuoteValidationException('Only an accepted quote can be converted to an invoice.');
        }

        $invoiceId = $this->invoices->save(new Invoice(
            organizationId: $organizationId,
            clientId: $quote->clientId,
            status: InvoiceStatus::Draft,
            subtotalCents: $quote->subtotalCents,
            taxCents: $quote->taxCents,
            totalCents: $quote->totalCents,
            quoteId: $quote->id,
            notes: $quote->notes,
        ));

        $quoteLines = $this->lineItems->findByParent(LineItemParent::Quote, $quoteId);
        $invoiceLines = [];
        foreach ($quoteLines as $line) {
            $invoiceLines[] = new LineItem(
                parentType: LineItemParent::Invoice,
                parentId: $invoiceId,
                description: $line->description,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                taxRateBps: $line->taxRateBps,
                sortOrder: $line->sortOrder,
            );
        }

        $this->lineItems->replaceForParent(LineItemParent::Invoice, $invoiceId, $invoiceLines);

        $saved = $this->invoices->findById($invoiceId);

        if ($saved === null) {
            throw new LogicException('Invoice disappeared immediately after conversion.');
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId);

        $this->audit->record($actorUserId, $organizationId, 'invoice.created', 'invoice', $invoiceId, null, InvoiceResponse::toArray($saved, $lines));

        return new InvoiceWithLines($saved, $lines);
    }
}
