<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;

/**
 * Test double that runs the unit of work immediately (no real transaction).
 * The callback receives a {@see NoopQueryExecutor}; use-case tests pass repo
 * factories that ignore the executor and return their in-memory repositories.
 */
final class ImmediateTransactionManager implements DatabaseTransactionManagerInterface
{
    private DatabaseQueryExecutorInterface $executor;

    public function __construct(?DatabaseQueryExecutorInterface $executor = null)
    {
        $this->executor = $executor ?? new NoopQueryExecutor();
    }

    public function transactional(callable $callback): mixed
    {
        return $callback($this->executor);
    }
}
