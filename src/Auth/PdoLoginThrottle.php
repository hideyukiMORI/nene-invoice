<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;

final readonly class PdoLoginThrottle implements LoginThrottleInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    public function countFailuresSince(string $ipAddress, string $since): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND created_at >= ?',
            [$ipAddress, $since],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function recordFailure(string $ipAddress): void
    {
        $this->query->execute(
            'INSERT INTO login_attempts (ip_address, created_at) VALUES (?, ?)',
            [$ipAddress, $this->clock->now()->format('Y-m-d H:i:s')],
        );
    }

    public function clearFailures(string $ipAddress): void
    {
        $this->query->execute('DELETE FROM login_attempts WHERE ip_address = ?', [$ipAddress]);
    }
}
