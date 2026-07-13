<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

/**
 * Appends demo-entry attribution lines (#658) to `<baseDir>/demo-entry.log`
 * instead of PHP's `error_log` (#661).
 *
 * On the Tier A shared-hosting target (HETEML), `error_log` output only lands
 * in the hosting control panel's log viewer — invisible over SSH, so it can't
 * be `tail -f`'d or `grep`'d for precise UTM/channel analysis. `var/` is the
 * only writable runtime directory there, same rationale and same fail-open
 * convention as {@see FileRateLimitStorage} and
 * {@see \NeneInvoice\RecurringInvoice\FileRecurringRunThrottle}: a write
 * failure (missing/unwritable dir, full disk, …) falls back to `error_log`
 * rather than losing the line or breaking the demo redirect.
 *
 * Invokable so it plugs directly into {@see DemoSessionSeater}'s
 * `(\Closure(string): void)` sink parameter via the `$sink(...)` first-class
 * callable syntax.
 */
final readonly class FileDemoEntryLogSink
{
    private const string FILENAME = 'demo-entry.log';

    public function __construct(private string $baseDir)
    {
    }

    public function __invoke(string $line): void
    {
        if (!is_dir($this->baseDir)) {
            @mkdir($this->baseDir, 0o775, true);
        }

        $file = $this->baseDir . '/' . self::FILENAME;
        $written = @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            error_log(sprintf(
                'NeNe Invoice: could not persist demo-entry log at %s; falling back to error_log.',
                $file,
            ));
            error_log($line);
        }
    }
}
