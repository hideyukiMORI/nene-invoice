<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryQuoteRepository implements QuoteRepositoryInterface
{
    /** @var array<int, Quote> */
    private array $byId = [];
    private int $nextId = 1;

    public function findById(int $id): ?Quote
    {
        $quote = $this->byId[$id] ?? null;

        return $quote !== null && !$quote->isDeleted ? $quote : null;
    }

    /** @return list<Quote> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (Quote $q): bool => $q->organizationId === $organizationId && !$q->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (Quote $q): bool => $q->organizationId === $organizationId && !$q->isDeleted,
        ));
    }

    public function save(Quote $quote): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = $this->withId($quote, $id);

        return $id;
    }

    public function update(Quote $quote): void
    {
        if ($quote->id === null || $this->findById($quote->id) === null) {
            throw new QuoteNotFoundException($quote->id ?? 0);
        }

        $this->byId[$quote->id] = $quote;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new QuoteNotFoundException($id);
        }

        $this->byId[$id] = $this->withId($existing, $id, isDeleted: true);
    }

    private function withId(Quote $quote, int $id, bool $isDeleted = false): Quote
    {
        return new Quote(
            organizationId: $quote->organizationId,
            clientId: $quote->clientId,
            quoteNumber: $quote->quoteNumber,
            status: $quote->status,
            subtotalCents: $quote->subtotalCents,
            taxCents: $quote->taxCents,
            totalCents: $quote->totalCents,
            issuedAt: $quote->issuedAt,
            validUntil: $quote->validUntil,
            notes: $quote->notes,
            isDeleted: $isDeleted,
            id: $id,
            createdAt: $quote->createdAt ?? '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );
    }
}
