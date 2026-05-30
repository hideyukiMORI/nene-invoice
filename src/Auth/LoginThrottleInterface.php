<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

/**
 * Tracks failed login attempts per source IP for brute-force throttling
 * (security: diagnostic F-2). Keyed by IP rather than account so a flood of
 * failures cannot lock a victim's account (lockout DoS).
 */
interface LoginThrottleInterface
{
    /** Number of failed attempts from this IP at or after the given datetime. */
    public function countFailuresSince(string $ipAddress, string $since): int;

    /** Record a failed attempt from this IP (now). */
    public function recordFailure(string $ipAddress): void;

    /** Clear this IP's failure history after a successful authentication. */
    public function clearFailures(string $ipAddress): void;
}
