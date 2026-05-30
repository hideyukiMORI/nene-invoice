<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use Nene2\Http\RequestScopedHolder;
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
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws QuoteNotFoundException
     * @throws QuoteValidationException
     */
    public function execute(?int $actorUserId, int $quoteId): InvoiceWithLines
    {
        $organizationId = $this->orgId->get();

        // The quote repo is org-scoped via the holder; cross-org → null.
        $quote = $this->quotes->findById($quoteId);

        if ($quote === null) {
            throw new QuoteNotFoundException($quoteId);
        }

        if ($quote->status !== QuoteStatus::Accepted) {
            throw new QuoteValidationException('Only an accepted quote can be converted to an invoice.');
        }

        // One invoice per quote: re-converting an already-converted quote would
        // create duplicate invoices (accounting integrity, diagnostic R2-2).
        if ($this->invoices->existsForQuote($quoteId)) {
            throw new QuoteValidationException('This quote has already been converted to an invoice.');
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
