<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use NeneInvoice\RecurringInvoice\FileRecurringRunThrottle;
use PHPUnit\Framework\TestCase;

final class FileRecurringRunThrottleTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/nene-invoice-throttle-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $dir = $this->baseDir . '/recurring-runs';
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
        @rmdir($this->baseDir);
    }

    public function test_first_claim_succeeds_then_same_day_is_denied(): void
    {
        $throttle = new FileRecurringRunThrottle($this->baseDir);

        self::assertTrue($throttle->claim(1, '2026-06-30'));
        self::assertFalse($throttle->claim(1, '2026-06-30'));
    }

    public function test_next_day_claims_again(): void
    {
        $throttle = new FileRecurringRunThrottle($this->baseDir);

        self::assertTrue($throttle->claim(1, '2026-06-30'));
        self::assertTrue($throttle->claim(1, '2026-07-01'));
    }

    public function test_organizations_are_independent(): void
    {
        $throttle = new FileRecurringRunThrottle($this->baseDir);

        self::assertTrue($throttle->claim(1, '2026-06-30'));
        self::assertTrue($throttle->claim(2, '2026-06-30'));
        self::assertFalse($throttle->claim(1, '2026-06-30'));
    }
}
