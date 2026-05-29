<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use LogicException;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class ChangeQuoteStatusUseCase
{
    public function __construct(
        private QuoteRepositoryInterface $quotes,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * @throws QuoteNotFoundException
     * @throws InvalidStateTransitionException
     */
    public function execute(int $organizationId, ?int $actorUserId, int $id, QuoteStatus $target): Quote
    {
        $quote = $this->quotes->findById($id);

        if ($quote === null || $quote->organizationId !== $organizationId) {
            throw new QuoteNotFoundException($id);
        }

        if (!$quote->status->canTransitionTo($target)) {
            throw new InvalidStateTransitionException($quote->status, $target);
        }

        // A quote is considered issued when first sent.
        $issuedAt = $target === QuoteStatus::Sent && $quote->issuedAt === null
            ? date('Y-m-d H:i:s')
            : $quote->issuedAt;

        $this->quotes->update(new Quote(
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

        $updated = $this->quotes->findById($id);

        if ($updated === null) {
            throw new LogicException('Quote disappeared immediately after status change.');
        }

        $this->audit->record(
            $actorUserId,
            $organizationId,
            'quote.status_changed',
            'quote',
            $id,
            QuoteResponse::toArray($quote),
            QuoteResponse::toArray($updated),
        );

        return $updated;
    }
}
