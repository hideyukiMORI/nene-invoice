<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\DocumentSequence\DocumentSequenceRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryDocumentSequenceRepository implements DocumentSequenceRepositoryInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    public function nextNumber(int $organizationId, string $docType, int $year): int
    {
        $key = $organizationId . ':' . $docType . ':' . $year;
        $next = ($this->counters[$key] ?? 0) + 1;
        $this->counters[$key] = $next;

        return $next;
    }
}
