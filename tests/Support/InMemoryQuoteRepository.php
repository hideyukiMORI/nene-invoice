<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Quote\Quote;
use NeneInvoice\Quote\QuoteNotFoundException;
use NeneInvoice\Quote\QuoteRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Quote\PdoQuoteRepository}.
 * `save` keeps the entity's org so tests can seed cross-tenant fixtures. The
 * holder defaults to organization 1 for single-org tests.
 */
final class InMemoryQuoteRepository implements QuoteRepositoryInterface
{
    /** @var array<int, Quote> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function findById(int $id): ?Quote
    {
        $quote = $this->byId[$id] ?? null;

        return $quote !== null && !$quote->isDeleted && $quote->organizationId === $this->orgId->get()
            ? $quote
            : null;
    }

    /** @return list<Quote> */
    public function findAll(int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (Quote $q): bool => $q->organizationId === $this->orgId->get() && !$q->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function count(): int
    {
        return count(array_filter(
            $this->byId,
            fn (Quote $q): bool => $q->organizationId === $this->orgId->get() && !$q->isDeleted,
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
