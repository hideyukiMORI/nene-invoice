<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * A do-nothing query executor for unit tests. Use-case tests drive in-memory
 * repositories, so the executor handed to {@see ImmediateTransactionManager}'s
 * callback is never actually queried — every method here fails loudly if it is,
 * surfacing a test that forgot to wire its repository factory to the fake.
 */
final class NoopQueryExecutor implements DatabaseQueryExecutorInterface
{
    /** @param array<string, mixed> $parameters */
    public function execute(string $sql, array $parameters = []): int
    {
        throw new LogicException('NoopQueryExecutor must not be queried in unit tests.');
    }

    /** @param array<string, mixed> $parameters */
    public function insert(string $sql, array $parameters = []): int
    {
        throw new LogicException('NoopQueryExecutor must not be queried in unit tests.');
    }

    public function lastInsertId(): int
    {
        throw new LogicException('NoopQueryExecutor must not be queried in unit tests.');
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        throw new LogicException('NoopQueryExecutor must not be queried in unit tests.');
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        throw new LogicException('NoopQueryExecutor must not be queried in unit tests.');
    }
}
