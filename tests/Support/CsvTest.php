<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    /**
     * @param list<string|int|null> $fields
     */
    private function row(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        self::assertIsResource($handle);
        Csv::putRow($handle, $fields);
        rewind($handle);
        $out = stream_get_contents($handle);
        fclose($handle);

        return $out === false ? '' : $out;
    }

    /**
     * @return list<array{0: string}>
     */
    public static function formulaTriggers(): array
    {
        return [['=SUM(1+1)'], ['+1'], ['-1+2'], ['@cmd'], ["\tx"], ["\rx"]];
    }

    #[DataProvider('formulaTriggers')]
    public function test_neutralizes_formula_leading_cells(string $dangerous): void
    {
        $out = $this->row([$dangerous]);

        // The value is prefixed with a single quote so spreadsheets treat it as text.
        self::assertStringContainsString("'" . $dangerous, $out);
    }

    public function test_leaves_safe_strings_untouched(): void
    {
        $out = $this->row(['株式会社サンプル']);

        self::assertStringContainsString('株式会社サンプル', $out);
        self::assertStringNotContainsString("'株式会社サンプル", $out);
    }

    public function test_passes_integers_through_as_numbers(): void
    {
        // A negative integer must stay numeric (not be quoted as a formula).
        $out = $this->row([-500, 1100]);

        self::assertStringContainsString('-500', $out);
        self::assertStringNotContainsString("'-500", $out);
        self::assertStringContainsString('1100', $out);
    }

    public function test_renders_null_as_empty(): void
    {
        $out = $this->row(['a', null, 'b']);

        self::assertStringContainsString('a,,b', $out);
    }
}
