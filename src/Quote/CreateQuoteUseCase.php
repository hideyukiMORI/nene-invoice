<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;

/**
 * Creates a draft quote: validates the client and lines, computes totals
 * (TaxCalculator, ADR 0004), allocates a quote number, persists the header and
 * line items, and records an audit entry.
 */
final readonly class CreateQuoteUseCase
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private LineItemRepositoryInterface $lineItems,
        private ClientRepositoryInterface $clients,
        private DocumentNumberGenerator $numbers,
        private TaxCalculator $taxCalculator,
        private AuditRecorderInterface $audit,
    ) {
    }

    /** @throws QuoteValidationException */
    public function execute(int $organizationId, ?int $actorUserId, CreateQuoteInput $input): QuoteWithLines
    {
        $client = $this->clients->findById($input->clientId);

        if ($client === null || $client->organizationId !== $organizationId) {
            throw new QuoteValidationException('The selected client does not exist in your organization.');
        }

        if ($input->lines === []) {
            throw new QuoteValidationException('A quote requires at least one line item.');
        }

        foreach ($input->lines as $line) {
            if (!in_array($line->taxRateBps, self::ALLOWED_TAX_RATES_BPS, true)) {
                throw new QuoteValidationException(sprintf('Tax rate %d bps is not allowed (use 1000 or 800).', $line->taxRateBps));
            }

            if ($line->quantity <= 0) {
                throw new QuoteValidationException('Line item quantity must be greater than zero.');
            }

            if ($line->unitPriceCents < 0) {
                throw new QuoteValidationException('Line item unit price must not be negative.');
            }
        }

        $totals = $this->taxCalculator->calculate($input->lines);
        $number = $this->numbers->next($organizationId, DocumentType::Quote, (int) date('Y'));

        $quoteId = $this->quotes->save(new Quote(
            organizationId: $organizationId,
            clientId: $input->clientId,
            quoteNumber: $number,
            status: QuoteStatus::Draft,
            subtotalCents: $totals->subtotalCents,
            taxCents: $totals->taxCents,
            totalCents: $totals->totalCents,
            validUntil: $input->validUntil,
            notes: $input->notes,
        ));

        $lineEntities = [];
        foreach ($input->lines as $index => $line) {
            $lineEntities[] = new LineItem(
                parentType: LineItemParent::Quote,
                parentId: $quoteId,
                description: $line->description,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                taxRateBps: $line->taxRateBps,
                sortOrder: $index,
            );
        }

        $this->lineItems->replaceForParent(LineItemParent::Quote, $quoteId, $lineEntities);

        $saved = $this->quotes->findById($quoteId);

        if ($saved === null) {
            throw new LogicException('Quote disappeared immediately after creation.');
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Quote, $quoteId);

        $this->audit->record($actorUserId, $organizationId, 'quote.created', 'quote', $quoteId, null, QuoteResponse::toArray($saved, $lines));

        return new QuoteWithLines($saved, $lines);
    }
}
