<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
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
use NeneInvoice\Support\Jst;

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
final readonly class CreateQuoteUseCase implements CreateQuoteUseCaseInterface
{
    /** Allowed consumption tax rates in basis points (accounting-compliance §3). */
    private const ALLOWED_TAX_RATES_BPS = [800, 1000];

    /**
     * @param Closure(DatabaseQueryExecutorInterface): QuoteRepositoryInterface $quotesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
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
        private Closure $auditFactory,
        private ClockInterface $clock,
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

        // Fiscal year and the "today" the validity period is measured from use the
        // JST wall clock so the Japanese calendar date is correct (ADR 0010).
        $todayJst = Jst::of($this->clock->now())->setTime(0, 0);
        $number   = $this->numbers->next(DocumentType::Quote, (int) $todayJst->format('Y'));

        // Validity: explicit input wins, else the company default period (有効期限)
        // measured from today (the draft's creation date).
        $validUntil = $input->validUntil
            ?? $this->companySettings->find()?->quoteValidUntilFrom($todayJst);

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
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

            $result = new QuoteWithLines($saved, $lineItems->findByParent(LineItemParent::Quote, $quoteId));

            // Audit inside the transaction: the record commits or rolls back with
            // the quote, so an unaudited creation cannot occur (Issue #352).
            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'quote.created', 'quote', $result->quote->id, null, QuoteResponse::toArray($result->quote, $result->lines));

            return $result;
        });
    }
}
