<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use DateTimeImmutable;
use NeneInvoice\Support\CsvImport;

/**
 * Parses a bank statement CSV into staged {@see BankTransaction} lines (#505)
 * using a per-bank {@see BankCsvColumnMapping}. Handles Shift_JIS (the common
 * Japanese bank encoding) as well as UTF-8, strips a UTF-8 BOM, normalizes
 * full-width digits, and tolerates thousands separators / currency marks in
 * amount cells.
 *
 * Parsing produces `unmatched` staging rows only — it records no payment and has
 * no accounting effect (matching/posting are later, compliance-reviewed steps).
 */
final class BankCsvParser
{
    public const MAX_BYTES = CsvImport::MAX_BYTES;

    public static function parse(string $raw, BankCsvColumnMapping $mapping, int $maxRows = 5000): BankCsvParseResult
    {
        if (strlen($raw) > self::MAX_BYTES) {
            return BankCsvParseResult::rejected(sprintf('ファイルサイズが上限（%d MB）を超えています。分割して取り込んでください。', intdiv(self::MAX_BYTES, 1_048_576)));
        }

        if (!$mapping->hasAmountSource()) {
            return BankCsvParseResult::rejected('列マッピングに金額列（符号付き金額、または入金/出金列）が指定されていません。');
        }

        $utf8 = self::toUtf8($raw, $mapping->encoding);
        if ($utf8 === null) {
            return BankCsvParseResult::rejected('ファイルの文字コードを UTF-8 に変換できませんでした。エンコーディング指定（utf-8 / shift_jis）を確認してください。');
        }

        $records = self::readRecords($utf8);
        if ($records === []) {
            return BankCsvParseResult::rejected('ファイルが空です。');
        }

        $line = 0;
        if ($mapping->hasHeader) {
            array_shift($records);
            $line = 1;
        }

        $transactions = [];
        $errors       = [];

        foreach ($records as $cells) {
            ++$line;

            if (self::isBlank($cells)) {
                continue;
            }

            $result = self::mapRow($cells, $mapping);

            if (is_string($result)) {
                $errors[] = ['line' => $line, 'reason' => $result];

                continue;
            }

            if ($result === null) {
                continue; // not a money movement (zero amount)
            }

            $transactions[] = $result;

            if (count($transactions) > $maxRows) {
                return BankCsvParseResult::rejected(sprintf('行数が上限（%d 行）を超えています。分割して取り込んでください。', $maxRows));
            }
        }

        return BankCsvParseResult::parsed($transactions, $errors);
    }

    /**
     * @param list<string> $cells
     * @return BankTransaction|string|null  a transaction, an error reason, or null to skip
     */
    private static function mapRow(array $cells, BankCsvColumnMapping $mapping): BankTransaction|string|null
    {
        $dateRaw = self::cell($cells, $mapping->valueDateColumn);
        if ($dateRaw === '') {
            return '日付列が空です。';
        }

        $date   = DateTimeImmutable::createFromFormat('!' . $mapping->dateFormat, $dateRaw);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return sprintf('日付『%s』を形式『%s』で解釈できませんでした。', $dateRaw, $mapping->dateFormat);
        }

        [$amountCents, $direction] = self::resolveAmount($cells, $mapping);
        if ($amountCents === 0) {
            return null;
        }

        return new BankTransaction(
            organizationId: 0, // forced from the org holder at persistence
            valueDate: $date->format('Y-m-d'),
            direction: $direction,
            amountCents: $amountCents,
            payerName: self::optionalCell($cells, $mapping->payerNameColumn),
            description: self::optionalCell($cells, $mapping->descriptionColumn),
            bankReference: self::optionalCell($cells, $mapping->bankReferenceColumn),
        );
    }

    /**
     * @param list<string> $cells
     * @return array{0: int, 1: BankTransactionDirection}
     */
    private static function resolveAmount(array $cells, BankCsvColumnMapping $mapping): array
    {
        if ($mapping->amountColumn !== null) {
            $signed = self::toInt(self::cell($cells, $mapping->amountColumn));

            return $signed >= 0
                ? [$signed, BankTransactionDirection::Credit]
                : [-$signed, BankTransactionDirection::Debit];
        }

        $credit = $mapping->creditColumn !== null ? self::toInt(self::cell($cells, $mapping->creditColumn)) : 0;
        if ($credit !== 0) {
            return [abs($credit), BankTransactionDirection::Credit];
        }

        $debit = $mapping->debitColumn !== null ? self::toInt(self::cell($cells, $mapping->debitColumn)) : 0;
        if ($debit !== 0) {
            return [abs($debit), BankTransactionDirection::Debit];
        }

        return [0, BankTransactionDirection::Credit];
    }

    /** @param list<string> $cells */
    private static function optionalCell(array $cells, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = self::cell($cells, $index);

        return $value !== '' ? $value : null;
    }

    /** @param list<string> $cells */
    private static function cell(array $cells, int $index): string
    {
        return isset($cells[$index]) ? trim((string) $cells[$index]) : '';
    }

    /** Strips currency marks / separators and normalizes full-width digits to an int. */
    private static function toInt(string $cell): int
    {
        $normalized = preg_replace('/[^0-9-]/', '', mb_convert_kana($cell, 'n'));
        if ($normalized === null || $normalized === '' || $normalized === '-') {
            return 0;
        }

        return (int) $normalized;
    }

    /** @return list<list<string>> */
    private static function readRecords(string $raw): array
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, $raw);
        rewind($handle);

        $records = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $records[] = array_map(static fn ($c): string => $c ?? '', $cells);
        }
        fclose($handle);

        return $records;
    }

    /** @param list<string> $cells */
    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function toUtf8(string $raw, string $encoding): ?string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $encoding = strtolower($encoding);

        if ($encoding === BankCsvColumnMapping::ENCODING_UTF8) {
            return mb_check_encoding($raw, 'UTF-8') ? $raw : null;
        }

        if ($encoding === BankCsvColumnMapping::ENCODING_SHIFT_JIS) {
            return self::fromShiftJis($raw);
        }

        // auto: trust valid UTF-8, otherwise assume Shift_JIS (CP932).
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return self::fromShiftJis($raw);
    }

    private static function fromShiftJis(string $raw): ?string
    {
        $converted = mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');

        return mb_check_encoding($converted, 'UTF-8') ? $converted : null;
    }
}
