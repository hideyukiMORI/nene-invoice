<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

use RuntimeException;
use ZipArchive;

/**
 * The web installer's "Feature B" — acquiring the application payload from a
 * manually uploaded release ZIP when `vendor/` is absent (a host that only
 * extracted `public_html/`, or a partial upload).
 *
 * ⚠️ Runs BEFORE `vendor/` exists, so this class must stay dependency-free: no
 * NENE2 toolkit, no other `src/` classes, only the global `ZipArchive` and
 * `RuntimeException`. `public_html/install.php` loads it with a direct
 * `require_once` (not the Composer autoloader, which isn't available yet).
 *
 * Security invariants (validated before any extraction):
 *  - every entry is rejected if it escapes the root (`..`, absolute path, drive letter) — zip-slip;
 *  - every top-level entry must be in {@see self::ALLOWED_TOP} — rejects unrelated / hostile ZIPs;
 *  - the SHA-256 the operator pastes from the release page must match — integrity.
 *
 * This round verifies the distributor SHA-256 only; ADR 0018 signature
 * verification lands with the updater / auto-fetch round.
 */
final class PayloadAcquisition
{
    /**
     * The only top-level entries a NeNe Invoice release ZIP may contain. Kept in
     * sync with what `tools/build-release.sh` ships (guarded by
     * {@see \NeneInvoice\Tests\Install\PayloadAcquisitionTest}) — a mismatch here
     * would reject the official ZIP.
     */
    public const array ALLOWED_TOP = [
        'src',
        'vendor',
        'database',
        'public_html',
        'resources',
        'composer.json',
        'composer.lock',
        'phinx.php',
        '.env.example',
        'README.md',
        'var',
    ];

    /** Upload ceiling; a host's effective limit (upload_max_filesize) may be smaller. */
    public const int MAX_UPLOAD_BYTES = 100 * 1024 * 1024;

    /**
     * Whether a ZIP entry name tries to write outside the extraction root
     * (zip-slip). Absolute paths, Windows drive letters and any `..` segment all
     * count as an escape (strict).
     */
    public static function entryEscapesRoot(string $entry): bool
    {
        $norm = str_replace('\\', '/', $entry);

        if ($norm === '' || $norm === '/') {
            return false;
        }

        // Absolute path (/foo) or Windows drive letter (C:\).
        if ($norm[0] === '/' || preg_match('#^[A-Za-z]:#', $norm) === 1) {
            return true;
        }

        foreach (explode('/', $norm) as $seg) {
            if ($seg === '..') {
                return true;
            }
        }

        return false;
    }

    /** The top-level (first) segment of a ZIP entry name. */
    public static function topSegment(string $entry): string
    {
        $norm  = ltrim(str_replace('\\', '/', $entry), '/');
        $slash = strpos($norm, '/');

        return $slash === false ? $norm : substr($norm, 0, $slash);
    }

    /**
     * Verify the SHA-256, then extract. The hash is checked with a constant-time
     * comparison BEFORE anything is unzipped; a blank, malformed or mismatched
     * hash all refuse extraction.
     */
    public static function verifyAndExtract(string $zipPath, string $expectedHash, string $root): void
    {
        $expected = strtolower(trim($expectedHash));

        if ($expected === '') {
            throw new RuntimeException('公式配布元のリリースページに記載された SHA-256 を入力してください。');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $expected) !== 1) {
            throw new RuntimeException('SHA-256 の形式が正しくありません（64 桁の 16 進数を入力してください）。');
        }

        $actual = hash_file('sha256', $zipPath);

        if ($actual === false) {
            throw new RuntimeException('アップロードされたファイルのハッシュを計算できませんでした。');
        }

        // Constant-time compare; verification always precedes extraction.
        if (!hash_equals($expected, strtolower($actual))) {
            throw new RuntimeException('SHA-256 が一致しません。公式配布元からダウンロードした ZIP と、そのページに記載のハッシュを確認してください。');
        }

        self::extract($zipPath, $root);
    }

    /**
     * Validate every entry (zip-slip + top-level allowlist) and only then extract
     * to $root — a full pass first so a hostile ZIP never partially extracts.
     */
    public static function extract(string $zipPath, string $root): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('zip 拡張（ZipArchive）が有効ではありません。');
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('アップロードされた ZIP を開けませんでした。ファイルが壊れていないか確認してください。');
        }

        try {
            if ($zip->numFiles === 0) {
                throw new RuntimeException('ZIP が空です。公式配布元の ZIP をアップロードしてください。');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if ($entry === false) {
                    throw new RuntimeException('ZIP のエントリを読み取れませんでした。');
                }

                if (self::entryEscapesRoot($entry)) {
                    throw new RuntimeException('ZIP に不正なパス（zip-slip の疑い）が含まれています。展開を中止しました。');
                }

                $top = self::topSegment($entry);

                if ($top !== '' && !in_array($top, self::ALLOWED_TOP, true)) {
                    throw new RuntimeException('想定外のエントリ「' . $top . '」が ZIP に含まれています。NeNe Invoice の配布 ZIP をアップロードしてください。');
                }
            }

            if ($zip->extractTo($root) !== true) {
                throw new RuntimeException('ZIP の展開に失敗しました。書き込み権限とディスク容量を確認してください。');
            }
        } finally {
            $zip->close();
        }
    }
}
