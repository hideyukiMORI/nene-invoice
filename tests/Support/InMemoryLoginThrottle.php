<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Auth\LoginThrottleInterface;

/**
 * In-memory login throttle for use-case tests. Records failures with the current
 * timestamp; {@see countFailuresSince()} compares against the given datetime.
 */
final class InMemoryLoginThrottle implements LoginThrottleInterface
{
    /** @var array<string, list<string>> ip => list of failure datetimes (Y-m-d H:i:s) */
    private array $failures = [];

    public function countFailuresSince(string $ipAddress, string $since): int
    {
        $count = 0;
        foreach ($this->failures[$ipAddress] ?? [] as $at) {
            if ($at >= $since) {
                $count++;
            }
        }

        return $count;
    }

    public function recordFailure(string $ipAddress): void
    {
        $this->failures[$ipAddress][] = date('Y-m-d H:i:s');
    }

    public function clearFailures(string $ipAddress): void
    {
        unset($this->failures[$ipAddress]);
    }
}
