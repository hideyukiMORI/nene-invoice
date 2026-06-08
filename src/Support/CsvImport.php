<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * The format gate for template-only CSV import (ADR 0011). Validates that the
 * uploaded bytes are UTF-8 and that the header row matches the expected template
 * header **exactly** (names and order), then returns the data rows keyed by
 * column for per-row domain validation. Generic across domains (clients, items).
 */
final class CsvImport
{
    /**
     * Defense-in-depth ceiling on the raw upload, aligned with the framework's
     * RequestSizeLimitMiddleware (1 MiB) — that middleware normally rejects an
     * oversized body with 413 before it reaches here; this is a second, domain-level
     * guard so the parser never builds an unbounded record array (Round 4 F3).
     */
    public const MAX_BYTES = 1_048_576;

    /**
     * @param list<string> $expectedHeader the template header, in order
     */
    public static function parse(string $raw, array $expectedHeader, int $maxRows = 5000, int $maxBytes = self::MAX_BYTES): CsvImportParse
    {
        // Bound memory up front: reject oversized uploads before reading any
        // records into memory (the row cap below only applies after parsing).
        if (strlen($raw) > $maxBytes) {
            return CsvImportParse::rejected(sprintf('ファイルサイズが上限（%d MB）を超えています。分割してインポートしてください。', intdiv($maxBytes, 1_048_576)));
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            return CsvImportParse::rejected('ファイルが UTF-8 ではありません。テンプレートを UTF-8 で保存し直してください。');
        }

        // Strip a leading UTF-8 BOM if Excel added one.
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $records = self::readRecords($raw);

        if ($records === []) {
            return CsvImportParse::rejected('ファイルが空です。テンプレートに行を追加してください。');
        }

        $header = array_map(static fn (string $cell): string => trim($cell), array_shift($records));

        if ($header !== $expectedHeader) {
            return CsvImportParse::rejected(self::headerMismatchReason($expectedHeader, $header));
        }

        $width = count($expectedHeader);
        $rows  = [];
        $line  = 1; // header is line 1

        foreach ($records as $cells) {
            ++$line;

            // Skip fully-blank lines (trailing newline, spacer rows).
            if (self::isBlank($cells)) {
                continue;
            }

            $wellFormed = count($cells) === $width;
            $mapped     = [];
            foreach ($expectedHeader as $i => $column) {
                $mapped[$column] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }

            $rows[] = ['line' => $line, 'cells' => $mapped, 'well_formed' => $wellFormed];

            if (count($rows) > $maxRows) {
                return CsvImportParse::rejected(sprintf('行数が上限（%d 行）を超えています。分割してインポートしてください。', $maxRows));
            }
        }

        return CsvImportParse::accepted($rows);
    }

    /**
     * @return list<list<string>>
     */
    private static function readRecords(string $raw): array
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, $raw);
        rewind($handle);

        $records = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            // fgetcsv yields [null] for a blank line; normalize to strings.
            $records[] = array_map(static fn ($c): string => $c ?? '', $cells);
        }
        fclose($handle);

        return $records;
    }

    /**
     * @param list<string> $cells
     */
    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private static function headerMismatchReason(array $expected, array $actual): string
    {
        $unknown = array_values(array_diff($actual, $expected));
        $missing = array_values(array_diff($expected, $actual));

        if ($unknown !== []) {
            return sprintf('不明な列『%s』があります。テンプレート以外の列は追加できません。', $unknown[0]);
        }

        if ($missing !== []) {
            return sprintf('列『%s』が見つかりません。最新のテンプレートをダウンロードしてください。', $missing[0]);
        }

        // Same columns, wrong order.
        return '列の順序がテンプレートと異なります。最新のテンプレートをダウンロードしてください。';
    }
}
