<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use NeneInvoice\Support\Csv;

/**
 * The clients import template (ADR 0011, `clients/v1`). The header is the single
 * source of truth shared by the template download and the import format gate —
 * the upload must match it exactly. The first `__template` column carries the
 * version for forward-compatibility; it may be left blank on new rows but, when
 * present, must equal {@see VERSION}.
 */
final class ClientImportTemplate
{
    public const VERSION = 'clients/v1';

    /** @var list<string> */
    public const HEADER = ['__template', 'id', '取引先名', 'カナ', '担当者', 'メール', '請求先住所', '登録番号'];

    /** UTF-8 (BOM) CSV with the header row only — the user adds rows beneath it. */
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
