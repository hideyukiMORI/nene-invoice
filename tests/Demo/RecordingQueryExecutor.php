<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Records every INSERT and exposes values by (table, column), resolved from the
 * statement's own column list — no hard-coded parameter indexes. Shared by the
 * demo-seed tests so every INSERT of every template can be inspected without a
 * database.
 */
final class RecordingQueryExecutor implements DatabaseQueryExecutorInterface
{
    /** @var list<array{sql: string, parameters: array<int|string, mixed>}> */
    private array $inserts = [];

    private int $nextId = 1;

    public function execute(string $sql, array $parameters = []): int
    {
        $this->record($sql, $parameters);

        return 1;
    }

    public function insert(string $sql, array $parameters = []): int
    {
        $this->record($sql, $parameters);

        return $this->nextId++;
    }

    public function lastInsertId(): int
    {
        return $this->nextId - 1;
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        return null;
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        return [];
    }

    /** @return list<mixed> Values bound to $column across all INSERTs into $table. */
    public function columnValues(string $table, string $column): array
    {
        $values = [];

        foreach ($this->inserts as $insert) {
            if (preg_match('/INSERT INTO ' . $table . '\s*\(([^)]+)\)/i', $insert['sql'], $m) !== 1) {
                continue;
            }
            $columns = array_map('trim', explode(',', $m[1]));
            $index   = array_search($column, $columns, true);
            if ($index === false) {
                continue;
            }
            // Positional parameters line up with the column list only when every
            // VALUES entry is a placeholder; the seeder embeds literals (e.g.
            // is_deleted 0), so count placeholders up to the column's position.
            if (preg_match('/VALUES\s*\((.+)\)/is', $insert['sql'], $vm) !== 1) {
                continue;
            }
            $slots       = array_map('trim', explode(',', $vm[1]));
            $paramIndex  = 0;
            foreach ($slots as $slotPosition => $slot) {
                if ($slotPosition === $index) {
                    $values[] = $slot === '?' ? ($insert['parameters'][$paramIndex] ?? null) : $slot;
                    break;
                }
                if ($slot === '?') {
                    $paramIndex++;
                }
            }
        }

        return $values;
    }

    /** @return int Number of INSERTs recorded into $table. */
    public function insertCount(string $table): int
    {
        $count = 0;
        foreach ($this->inserts as $insert) {
            if (preg_match('/INSERT INTO ' . $table . '\s*\(/i', $insert['sql']) === 1) {
                $count++;
            }
        }

        return $count;
    }

    /** @param array<int|string, mixed> $parameters */
    private function record(string $sql, array $parameters): void
    {
        if (stripos(ltrim($sql), 'INSERT') === 0) {
            $this->inserts[] = ['sql' => $sql, 'parameters' => $parameters];
        }
    }
}
