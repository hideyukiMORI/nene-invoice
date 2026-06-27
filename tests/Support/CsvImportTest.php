<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Support\CsvImport;
use PHPUnit\Framework\TestCase;

final class CsvImportTest extends TestCase
{
    private const HEADER = ['__template', 'name'];

    public function test_rejects_non_utf8(): void
    {
        $parse = CsvImport::parse("\xFF\xFE__template,name\n", self::HEADER);

        self::assertNotNull($parse->formatError);
        self::assertStringContainsString('UTF-8', $parse->formatError);
    }

    public function test_rejects_oversized_upload_before_parsing(): void
    {
        // Exceed a tiny byte cap; rejection must happen on size, not header/rows.
        $parse = CsvImport::parse(str_repeat('a', 1_001), self::HEADER, maxRows: 5000, maxBytes: 1_000);

        self::assertNotNull($parse->formatError);
        self::assertStringContainsString('ファイルサイズ', $parse->formatError);
        self::assertSame([], $parse->rows);
    }

    public function test_strips_bom_and_accepts_matching_header(): void
    {
        $parse = CsvImport::parse("\xEF\xBB\xBF__template,name\nclients/v1,Acme\n", self::HEADER);

        self::assertNull($parse->formatError);
        self::assertCount(1, $parse->rows);
        self::assertSame(['__template' => 'clients/v1', 'name' => 'Acme'], $parse->rows[0]['cells']);
        self::assertSame(2, $parse->rows[0]['line']);
        self::assertTrue($parse->rows[0]['well_formed']);
    }

    public function test_rejects_unknown_column(): void
    {
        $parse = CsvImport::parse("__template,name,phone\n", self::HEADER);

        self::assertNotNull($parse->formatError);
        self::assertStringContainsString('phone', $parse->formatError);
    }

    public function test_rejects_missing_column(): void
    {
        $parse = CsvImport::parse("__template\n", self::HEADER);

        self::assertNotNull($parse->formatError);
        self::assertStringContainsString('name', $parse->formatError);
    }

    public function test_skips_blank_lines_and_flags_width_mismatch(): void
    {
        $parse = CsvImport::parse("__template,name\n,Acme\n\n,Beta,extra\n", self::HEADER);

        self::assertNull($parse->formatError);
        self::assertCount(2, $parse->rows);
        self::assertTrue($parse->rows[0]['well_formed']);
        self::assertFalse($parse->rows[1]['well_formed']); // 3 cells vs 2
        self::assertSame(4, $parse->rows[1]['line']); // blank line 3 was skipped
    }

    public function test_accepts_upload_exactly_at_byte_cap_and_rejects_one_over(): void
    {
        // The cap is `strlen > maxBytes`: exactly at the cap is accepted, one over rejected.
        $csv = "__template,name\nclients/v1,Acme\n";
        $size = strlen($csv);

        $atCap = CsvImport::parse($csv, self::HEADER, maxRows: 5000, maxBytes: $size);
        self::assertNull($atCap->formatError);

        $overByOne = CsvImport::parse($csv, self::HEADER, maxRows: 5000, maxBytes: $size - 1);
        self::assertNotNull($overByOne->formatError);
        self::assertStringContainsString('ファイルサイズ', $overByOne->formatError);
    }

    public function test_accepts_exactly_max_rows_and_rejects_one_more(): void
    {
        // The cap is `count(rows) > maxRows`: exactly maxRows is accepted, +1 rejected.
        $header = "__template,name\n";

        $atCap = CsvImport::parse($header . "c/v1,A\nc/v1,B\n", self::HEADER, maxRows: 2);
        self::assertNull($atCap->formatError);
        self::assertCount(2, $atCap->rows);

        $over = CsvImport::parse($header . "c/v1,A\nc/v1,B\nc/v1,C\n", self::HEADER, maxRows: 2);
        self::assertNotNull($over->formatError);
        self::assertStringContainsString('行数', $over->formatError);
    }

    public function test_rejects_truly_empty_file(): void
    {
        $parse = CsvImport::parse('', self::HEADER);

        self::assertNotNull($parse->formatError);
        self::assertStringContainsString('空', $parse->formatError);
    }

    public function test_header_only_is_accepted_with_zero_rows(): void
    {
        // Contrast with the truly-empty case: a header and no data rows is valid.
        $parse = CsvImport::parse("__template,name\n", self::HEADER);

        self::assertNull($parse->formatError);
        self::assertSame([], $parse->rows);
    }
}
