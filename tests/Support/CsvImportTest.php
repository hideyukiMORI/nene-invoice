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
}
