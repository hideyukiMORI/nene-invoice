<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\DocumentSequence\DocumentSequenceRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). The organization is read from
 * the request-scoped org holder, mirroring
 * {@see \NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository}. The holder
 * defaults to organization 1 for single-org tests.
 */
final class InMemoryDocumentSequenceRepository implements DocumentSequenceRepositoryInterface
{
    /** @var array<string, int> */
    private array $counters = [];

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

    public function nextNumber(string $docType, int $year): int
    {
        $key = $this->orgId->get() . ':' . $docType . ':' . $year;
        $next = ($this->counters[$key] ?? 0) + 1;
        $this->counters[$key] = $next;

        return $next;
    }
}
