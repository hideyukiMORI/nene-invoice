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

final readonly class ChangeQuoteStatusUseCase implements ChangeQuoteStatusUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): QuoteRepositoryInterface $quotesFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $quotesFactory,
        private Closure $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws QuoteNotFoundException
     * @throws InvalidStateTransitionException
     */
    public function execute(?int $actorUserId, int $id, QuoteStatus $target): Quote
    {
        $organizationId = $this->orgId->get();

        $quote = $this->quotes->findById($id);

        if ($quote === null) {
            throw new QuoteNotFoundException($id);
        }

        if (!$quote->status->canTransitionTo($target)) {
            throw new InvalidStateTransitionException($quote->status, $target);
        }

        // A quote is considered issued when first sent.
        $issuedAt = $target === QuoteStatus::Sent && $quote->issuedAt === null
            ? $this->clock->now()->format('Y-m-d H:i:s')
            : $quote->issuedAt;

        // The update and its audit record commit atomically (Issue #352).
        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $id,
            $target,
            $quote,
            $issuedAt,
        ): Quote {
            $quotes = ($this->quotesFactory)($exec);

            $quotes->update(new Quote(
                organizationId: $quote->organizationId,
                clientId: $quote->clientId,
                quoteNumber: $quote->quoteNumber,
                status: $target,
                subtotalCents: $quote->subtotalCents,
                taxCents: $quote->taxCents,
                totalCents: $quote->totalCents,
                issuedAt: $issuedAt,
                validUntil: $quote->validUntil,
                notes: $quote->notes,
                id: $quote->id,
                createdAt: $quote->createdAt,
                updatedAt: $quote->updatedAt,
            ));

            $updated = $quotes->findById($id);

            if ($updated === null) {
                throw new LogicException('Quote disappeared immediately after status change.');
            }

            ($this->auditFactory)($exec)->record(
                $actorUserId,
                $organizationId,
                'quote.status_changed',
                'quote',
                $id,
                QuoteResponse::toArray($quote),
                QuoteResponse::toArray($updated),
            );

            return $updated;
        });
    }
}
