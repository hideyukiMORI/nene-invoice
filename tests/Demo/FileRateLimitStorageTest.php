<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use NeneInvoice\Demo\FileRateLimitStorage;
use NeneInvoice\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * The demo-start throttle state must survive across PHP processes (shared
 * hosting = one process per request), so it lives in files under
 * `var/rate-limits/`. These tests exercise the fixed-window semantics the
 * NENE2 `CountingDemoCapacityGuard` relies on: counting within a window,
 * resetting after expiry, and isolating unrelated keys.
 */
final class FileRateLimitStorageTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/ni-rate-limit-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->baseDir . '/rate-limits/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->baseDir . '/rate-limits');
        @rmdir($this->baseDir);
    }

    public function test_counts_hits_within_the_window(): void
    {
        $storage = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T00:00:00Z'));

        $first = $storage->hit('demo-start:ip:203.0.113.7', 3600);
        $second = $storage->hit('demo-start:ip:203.0.113.7', 3600);
        $third = $storage->hit('demo-start:ip:203.0.113.7', 3600);

        self::assertSame(1, $first['count']);
        self::assertSame(2, $second['count']);
        self::assertSame(3, $third['count']);
        self::assertSame($first['reset_at'], $third['reset_at'], 'window expiry must not move on subsequent hits');
    }

    public function test_expired_window_resets_the_count(): void
    {
        $key = 'demo-start:ip:203.0.113.7';

        $storage = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T00:00:00Z'));
        $storage->hit($key, 3600);
        $storage->hit($key, 3600);

        // Same state files, one second past the window: a fresh window of 1.
        $later = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T01:00:01Z'));
        $afterExpiry = $later->hit($key, 3600);

        self::assertSame(1, $afterExpiry['count']);
    }

    public function test_keys_have_independent_windows(): void
    {
        $storage = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T00:00:00Z'));

        $storage->hit('demo-start:ip:203.0.113.7', 3600);
        $storage->hit('demo-start:ip:203.0.113.7', 3600);
        $other = $storage->hit('demo-start:ip:198.51.100.9', 3600);

        self::assertSame(1, $other['count']);
    }

    public function test_state_survives_across_instances_like_separate_processes(): void
    {
        $key = 'demo-start:ip:203.0.113.7';

        (new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T00:00:00Z')))->hit($key, 3600);
        $second = (new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-09T00:00:30Z')))->hit($key, 3600);

        self::assertSame(2, $second['count']);
    }
}
