<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Install;

use NeneInvoice\Install\PayloadAcquisition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * The installer's "Feature B" payload acquisition — the security-critical part
 * (zip-slip guard, top-level allowlist, SHA-256 verification) extracted out of
 * `public_html/install.php` so it can actually be unit tested.
 */
final class PayloadAcquisitionTest extends TestCase
{
    /** @var list<string> */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function entryProvider(): iterable
    {
        yield 'normal file'      => ['src/Foo.php', false];
        yield 'top-level file'   => ['composer.json', false];
        yield 'empty'            => ['', false];
        yield 'bare slash'       => ['/', false];
        yield 'parent traversal' => ['../etc/passwd', true];
        yield 'nested traversal' => ['src/../../evil', true];
        yield 'absolute path'    => ['/etc/passwd', true];
        yield 'drive letter'     => ['C:\\Windows\\evil', true];
        yield 'backslash parent' => ['..\\evil', true];
    }

    #[DataProvider('entryProvider')]
    public function test_entry_escapes_root(string $entry, bool $expected): void
    {
        self::assertSame($expected, PayloadAcquisition::entryEscapesRoot($entry));
    }

    public function test_top_segment(): void
    {
        self::assertSame('src', PayloadAcquisition::topSegment('src/Foo.php'));
        self::assertSame('composer.json', PayloadAcquisition::topSegment('composer.json'));
        self::assertSame('src', PayloadAcquisition::topSegment('/src/Foo.php'));
        self::assertSame('public_html', PayloadAcquisition::topSegment('public_html\\admin\\index.php'));
    }

    public function test_verify_and_extract_rejects_blank_hash(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}']);
        $this->expectException(RuntimeException::class);
        PayloadAcquisition::verifyAndExtract($zip, '', $this->makeDir());
    }

    public function test_verify_and_extract_rejects_malformed_hash(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}']);
        $this->expectException(RuntimeException::class);
        PayloadAcquisition::verifyAndExtract($zip, 'not-a-sha', $this->makeDir());
    }

    public function test_verify_and_extract_rejects_mismatched_hash(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}']);
        $wrong = str_repeat('0', 64);
        $this->expectException(RuntimeException::class);
        PayloadAcquisition::verifyAndExtract($zip, $wrong, $this->makeDir());
    }

    public function test_verify_and_extract_accepts_matching_hash_and_extracts(): void
    {
        $zip = $this->makeZip(['src/Foo.php' => '<?php', 'composer.json' => '{}']);
        $hash = hash_file('sha256', $zip);
        self::assertIsString($hash);
        $dest = $this->makeDir();

        PayloadAcquisition::verifyAndExtract($zip, $hash, $dest);

        self::assertFileExists($dest . '/src/Foo.php');
        self::assertFileExists($dest . '/composer.json');
    }

    public function test_extract_rejects_unexpected_top_level_entry(): void
    {
        $zip = $this->makeZip(['src/Foo.php' => '<?php', 'evil/malware.sh' => 'rm -rf /']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/想定外のエントリ/');
        PayloadAcquisition::extract($zip, $this->makeDir());
    }

    public function test_extract_rejects_empty_zip(): void
    {
        $zip = $this->makeZip([]);
        $this->expectException(RuntimeException::class);
        PayloadAcquisition::extract($zip, $this->makeDir());
    }

    public function test_extract_accepts_all_allowed_top_level_entries(): void
    {
        $entries = [];
        foreach (PayloadAcquisition::ALLOWED_TOP as $top) {
            // Directories get a child file; plain files are added directly.
            $entries[str_contains($top, '.') ? $top : $top . '/keep'] = 'x';
        }
        $zip = $this->makeZip($entries);
        $dest = $this->makeDir();

        PayloadAcquisition::extract($zip, $dest);

        self::assertFileExists($dest . '/src/keep');
        self::assertFileExists($dest . '/composer.json');
        self::assertFileExists($dest . '/README.md');
    }

    /**
     * Drift guard: every top-level entry `tools/build-release.sh` ships must be in
     * the allowlist, otherwise Feature B would reject the official release ZIP —
     * exactly the bug that motivated this class (resources / README.md /
     * composer.lock were missing).
     */
    public function test_allowlist_covers_everything_build_release_ships(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/build-release.sh');

        // Single-segment staging destinations: "$STAGE/<name>".
        preg_match_all('#"\$STAGE/([^/"]+)"#', $script, $matches);
        $shipped = array_unique($matches[1]);

        // vendor/ and composer.lock are produced by `composer update`, not copied.
        $shipped[] = 'vendor';
        $shipped[] = 'composer.lock';

        self::assertNotEmpty($shipped, 'Expected to parse staged entries from build-release.sh.');

        foreach ($shipped as $top) {
            self::assertContains(
                $top,
                PayloadAcquisition::ALLOWED_TOP,
                "build-release.sh ships '{$top}' but PayloadAcquisition::ALLOWED_TOP omits it — Feature B would reject the release ZIP.",
            );
        }
    }

    /**
     * @param array<string, string> $entries entry name => contents
     */
    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nene_pa_') . '.zip';
        $this->tempPaths[] = $path;

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function makeDir(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nene_pa_dir_');
        self::assertIsString($path);
        unlink($path);
        mkdir($path, 0755, true);
        $this->tempPaths[] = $path;

        return $path;
    }

    private function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
