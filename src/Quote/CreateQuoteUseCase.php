<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Closure;
use DateTimeImmutable;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;

/**
 * Creates a draft quote: validates the client and lines, computes totals
 * (TaxCalculator, ADR 0004), allocates a quote number, then persists the header
 * and line items **atomically** (one transaction) and records an audit entry.
 *
 * The header + line writes run inside {@see DatabaseTransactionManagerInterface},
 * so the repositories are rebuilt from the transaction-bound executor via the
 * injected factories (a pre-built repository would use a different connection and
 * escape the transaction). Reads/validation and audit stay outside.
 */
final readonly class CreateQuoteUseCase
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): QuoteRepositoryInterface $quotesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $quotesFactory,
        private Closure $lineItemsFactory,
        private ClientRepositoryInterface $clients,
        private CompanySettingsRepositoryInterface $companySettings,
        private DocumentNumberGenerator $numbers,
        private TaxCalculator $taxCalculator,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws QuoteValidationException */
    public function execute(?int $actorUserId, CreateQuoteInput $input): QuoteWithLines
    {
        $organizationId = $this->orgId->get();

        // The client repo is org-scoped, so a cross-org client surfaces as null.
        $client = $this->clients->findById($input->clientId);

        if ($client === null) {
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
        $number = $this->numbers->next(DocumentType::Quote, (int) date('Y'));

        // Validity: explicit input wins, else the company default period (有効期限)
        // measured from today (the draft's creation date).
        $validUntil = $input->validUntil
            ?? $this->companySettings->find()?->quoteValidUntilFrom(new DateTimeImmutable('today'));

        $result = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $organizationId,
            $input,
            $number,
            $totals,
            $validUntil,
        ): QuoteWithLines {
            $quotes    = ($this->quotesFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $quoteId = $quotes->save(new Quote(
                organizationId: $organizationId,
                clientId: $input->clientId,
                quoteNumber: $number,
                status: QuoteStatus::Draft,
                subtotalCents: $totals->subtotalCents,
                taxCents: $totals->taxCents,
                totalCents: $totals->totalCents,
                validUntil: $validUntil,
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

            $lineItems->replaceForParent(LineItemParent::Quote, $quoteId, $lineEntities);

            $saved = $quotes->findById($quoteId);

            if ($saved === null) {
                throw new LogicException('Quote disappeared immediately after creation.');
            }

            return new QuoteWithLines($saved, $lineItems->findByParent(LineItemParent::Quote, $quoteId));
        });

        $this->audit->record($actorUserId, $organizationId, 'quote.created', 'quote', $result->quote->id, null, QuoteResponse::toArray($result->quote, $result->lines));

        return $result;
    }
}
