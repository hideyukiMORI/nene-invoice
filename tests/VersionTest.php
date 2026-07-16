<?php

declare(strict_types=1);

namespace NeneInvoice\Tests;

use NeneInvoice\Version;
use PHPUnit\Framework\TestCase;

/**
 * Proves the version single-source contract (#425 / records #586): the string
 * comes from the repo-root VERSION file, is trimmed, and a missing/blank file
 * yields null so callers can omit `version` rather than fabricate one.
 */
final class VersionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . uniqid('invoice-version-', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $file = $this->dir . '/VERSION';
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function writeVersion(string $contents): string
    {
        $path = $this->dir . '/VERSION';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_reads_and_trims_the_version_file(): void
    {
        self::assertSame('1.0.0', Version::current($this->writeVersion("1.0.0\n")));
        self::assertSame('0.0.0-dev', Version::current($this->writeVersion('  0.0.0-dev  ')));
    }

    public function test_missing_file_yields_null(): void
    {
        self::assertNull(Version::current($this->dir . '/does-not-exist'));
    }

    public function test_blank_file_yields_null(): void
    {
        self::assertNull(Version::current($this->writeVersion("   \n")));
    }

    public function test_committed_root_version_file_is_readable(): void
    {
        // The shipped repo-root VERSION must exist and parse (bumped at release).
        self::assertNotNull(Version::current());
    }
}
