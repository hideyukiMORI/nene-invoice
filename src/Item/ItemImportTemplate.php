<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use NeneInvoice\Support\Csv;

/**
 * The items import/export template (ADR 0011, `items/v1`). The header is the
 * single source of truth shared by the template download, the export (so an
 * export round-trips into an import), and the import format gate. `標準単価` is
 * whole yen (JPY stores 1:1 as cents — no ×100); `標準税率` is a percent (10 or
 * 8). The `__template` column carries the version; blank is allowed on new rows
 * but, when present, must equal {@see VERSION}.
 */
final class ItemImportTemplate
{
    public const VERSION = 'items/v1';

    /** @var list<string> */
    public const HEADER = ['__template', 'id', '品目名', '標準単価', '標準税率'];

    /** UTF-8 (BOM) CSV with the header row only. */
    public static function csv(): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        fwrite($handle, "\xEF\xBB\xBF");
        Csv::putRow($handle, self::HEADER);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
