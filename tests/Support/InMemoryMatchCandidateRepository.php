<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\BankTransaction\MatchCandidateRepositoryInterface;
use NeneInvoice\BankTransaction\OpenReceivable;

/**
 * In-memory fake for {@see \NeneInvoice\BankTransaction\SuggestMatchesUseCase}
 * tests: returns a canned set of open receivables (the ledger read is covered by
 * {@see \NeneInvoice\BankTransaction\PdoMatchCandidateRepository} integration).
 */
final class InMemoryMatchCandidateRepository implements MatchCandidateRepositoryInterface
{
    /** @param list<OpenReceivable> $receivables */
    public function __construct(private array $receivables = [])
    {
    }

    /** @return list<OpenReceivable> */
    public function findOpenReceivables(): array
    {
        return $this->receivables;
    }
}
