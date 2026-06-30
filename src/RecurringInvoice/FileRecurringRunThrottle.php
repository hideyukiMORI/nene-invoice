<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * File-backed {@see RecurringRunThrottleInterface}. Writes one marker file per
 * organization under `<baseDir>/recurring-runs/`, holding the last JST date the
 * inline run executed.
 *
 * No DB table — it works on shared hosting where `var/` is the only writable
 * runtime directory and cron is unavailable. The claim is best-effort: a marker
 * write failure is logged and the run is still allowed (the underlying
 * {@see GenerateDueRecurringInvoicesUseCase} is idempotent, so a degraded
 * "runs every request" mode is safe, just not optimal). A rare concurrent
 * double-claim is harmless for the same reason.
 */
final readonly class FileRecurringRunThrottle implements RecurringRunThrottleInterface
{
    public function __construct(private string $baseDir)
    {
    }

    public function claim(int $organizationId, string $date): bool
    {
        $dir  = $this->baseDir . '/recurring-runs';
        $file = $dir . '/org-' . $organizationId . '.txt';

        if (is_file($file) && trim((string) @file_get_contents($file)) === $date) {
            return false; // already ran today
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        if (@file_put_contents($file, $date, LOCK_EX) === false) {
            error_log(sprintf(
                'NeNe Invoice: could not persist recurring run marker at %s; the inline run will not be throttled.',
                $file,
            ));
        }

        return true;
    }
}
