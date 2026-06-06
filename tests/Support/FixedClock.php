<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Nene2\Http\ClockInterface;

/**
 * Deterministic {@see ClockInterface} for tests: always returns the same UTC
 * instant so time-dependent use cases (issue date, due-date math, dashboard
 * buckets, token expiry) can be asserted without real-time drift.
 */
final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    /** Defaults to a fixed UTC instant; pass an ISO-8601 string to override. */
    public function __construct(string $instant = '2026-06-06T03:00:00Z')
    {
        $this->now = new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
