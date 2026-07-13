<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use NeneInvoice\Demo\FileDemoEntryLogSink;
use PHPUnit\Framework\TestCase;

/**
 * The demo-entry attribution line (#658) is appended to `var/demo-entry.log`
 * rather than `error_log` (#661) so it is `tail -f`/`grep`-able over SSH on
 * the Tier A shared-hosting target, where `error_log` only reaches the
 * hosting control panel. Mirrors {@see FileRateLimitStorageTest}'s temp-dir
 * convention.
 */
final class FileDemoEntryLogSinkTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/ni-demo-entry-log-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        @unlink($this->baseDir . '/demo-entry.log');
        @rmdir($this->baseDir);
    }

    public function test_appends_a_line_to_the_log_file_under_base_dir(): void
    {
        $sink = new FileDemoEntryLogSink($this->baseDir);

        $sink('NeNe Invoice: demo-entry slug=demo-abcd utm_source=facebook');

        $contents = (string) file_get_contents($this->baseDir . '/demo-entry.log');
        self::assertSame(
            'NeNe Invoice: demo-entry slug=demo-abcd utm_source=facebook' . PHP_EOL,
            $contents,
        );
    }

    public function test_creates_the_base_dir_when_missing(): void
    {
        self::assertDirectoryDoesNotExist($this->baseDir);

        (new FileDemoEntryLogSink($this->baseDir))('NeNe Invoice: demo-entry slug=demo-zzzz');

        self::assertDirectoryExists($this->baseDir);
        self::assertFileExists($this->baseDir . '/demo-entry.log');
    }

    public function test_appends_multiple_lines_in_order(): void
    {
        $sink = new FileDemoEntryLogSink($this->baseDir);

        $sink('NeNe Invoice: demo-entry slug=demo-one');
        $sink('NeNe Invoice: demo-entry slug=demo-two');

        $lines = explode(PHP_EOL, trim((string) file_get_contents($this->baseDir . '/demo-entry.log')));
        self::assertSame(
            ['NeNe Invoice: demo-entry slug=demo-one', 'NeNe Invoice: demo-entry slug=demo-two'],
            $lines,
        );
    }

    public function test_falls_back_to_error_log_when_the_file_cannot_be_written(): void
    {
        // A base dir that is itself an existing *file* can never become a
        // writable directory: is_dir() is false, @mkdir() fails silently, and
        // the subsequent file_put_contents() also fails — exercising the
        // fail-open branch without needing chmod/root tricks.
        $blockingFile = sys_get_temp_dir() . '/ni-demo-entry-log-blocker-' . bin2hex(random_bytes(6));
        file_put_contents($blockingFile, 'not a directory');

        try {
            $sink = new FileDemoEntryLogSink($blockingFile);

            // Must not throw: the fallback path (error_log) absorbs the
            // failure so a demo-entry redirect is never broken by logging.
            $sink('NeNe Invoice: demo-entry slug=demo-fallback');
            self::expectNotToPerformAssertions();
        } finally {
            @unlink($blockingFile);
        }
    }
}
