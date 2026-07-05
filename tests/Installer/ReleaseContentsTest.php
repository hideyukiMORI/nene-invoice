<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Installer;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Tier A release ZIP against missing runtime files (#550).
 *
 * `tools/build-release.sh` copies a hand-listed (allowlist) set of directories
 * into the release artifact and provisions `vendor/` from Packagist in staging.
 * When code loads a file from a repository-root directory at
 * runtime (e.g. {@see \NeneInvoice\Pdf\MpdfFactory} reading the bundled IPAex
 * fonts from `resources/fonts/`), that directory MUST be in the release — else a
 * fresh install works for everything except that path, as happened when
 * `resources/` was omitted and every invoice/quote PDF 500'd with
 * "Cannot find TTF TrueType font file". This test fails if `src/` references a
 * repo-root directory at runtime that the release script does not copy.
 */
final class ReleaseContentsTest extends TestCase
{
    private const CORE_DIRS = ['src', 'database', 'public_html', 'resources'];

    private static function releaseScript(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/tools/build-release.sh');
    }

    public function test_release_bundles_the_core_runtime_directories(): void
    {
        $script = self::releaseScript();

        foreach (self::CORE_DIRS as $dir) {
            self::assertMatchesRegularExpression(
                '#cp\s+-r\s+"\$ROOT/' . preg_quote($dir, '#') . '"#',
                $script,
                "build-release.sh must copy the '{$dir}/' directory into the release.",
            );
        }
    }

    /**
     * Every repo-root directory that `src/` loads at runtime (via
     * `__DIR__ . '/../../<dir>/...'`) must be shipped by the release script.
     */
    public function test_release_ships_every_repo_root_dir_that_src_loads_at_runtime(): void
    {
        $root   = dirname(__DIR__, 2);
        $script = self::releaseScript();

        /** @var array<string, true> $referenced */
        $referenced = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            if (preg_match_all('#__DIR__\s*\.\s*\'/\.\./\.\./([^/\']+)#', $code, $m) > 0) {
                foreach ($m[1] as $dir) {
                    $referenced[$dir] = true;
                }
            }
        }

        self::assertArrayHasKey('resources', $referenced, 'Expected MpdfFactory to reference resources/ (test sanity).');

        foreach (array_keys($referenced) as $dir) {
            self::assertMatchesRegularExpression(
                '#cp\s+-r\s+"\$ROOT/' . preg_quote($dir, '#') . '"#',
                $script,
                "src/ loads '{$dir}/' at runtime, so build-release.sh must ship it (#550).",
            );
        }
    }

    /**
     * vendor/ is NOT copied from the working tree: the hardened release script
     * resolves hideyukimori/nene2 ^1.6 (and every prod dependency) from Packagist
     * in a staging dir, then asserts the NENE2 install toolkit materialised. This
     * keeps path-repo / symlink dev builds out of the shipped artifact (#576).
     */
    public function test_release_provisions_vendor_from_packagist_not_the_working_tree(): void
    {
        $script = self::releaseScript();

        self::assertMatchesRegularExpression(
            '#composer\s+update\s+--no-dev#',
            $script,
            'build-release.sh must resolve prod vendor from Packagist (composer update --no-dev), not copy the working-tree vendor.',
        );
        self::assertStringContainsString(
            'vendor/hideyukimori/nene2/src/Install/PayloadInstaller.php',
            $script,
            'build-release.sh must assert the NENE2 install toolkit materialised in the staged vendor (#576).',
        );
        self::assertStringNotContainsString(
            'cp -r "$ROOT/vendor"',
            $script,
            'build-release.sh must not copy the working-tree vendor (path-repo / symlink leak risk, #576).',
        );
    }
}
