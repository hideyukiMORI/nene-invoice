<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use NeneInvoice\BankTransaction\BankCsvColumnMapping;
use NeneInvoice\BankTransaction\BankCsvParser;
use NeneInvoice\BankTransaction\BankImportPresets;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use PHPUnit\Framework\TestCase;

final class BankCsvParserTest extends TestCase
{
    public function test_signed_amount_layout_splits_credit_and_debit(): void
    {
        $csv = <<<CSV
        日付,金額,依頼人,摘要
        2026-06-30,"11,000",カ）サンプル,振込
        2026-06-29,-3000,,ATM出金
        2026-06-28,0,,残高のみ
        CSV;

        $result = BankCsvParser::parse($csv, BankImportPresets::signedAmount());

        self::assertNull($result->formatError);
        self::assertSame([], $result->errors);
        self::assertCount(2, $result->transactions); // zero-amount row skipped

        $credit = $result->transactions[0];
        self::assertSame('2026-06-30', $credit->valueDate);
        self::assertSame(BankTransactionDirection::Credit, $credit->direction);
        self::assertSame(11000, $credit->amountCents);
        self::assertSame('カ）サンプル', $credit->payerName);
        self::assertSame('振込', $credit->description);

        $debit = $result->transactions[1];
        self::assertSame(BankTransactionDirection::Debit, $debit->direction);
        self::assertSame(3000, $debit->amountCents);
    }

    public function test_separate_credit_debit_columns(): void
    {
        $csv = <<<CSV
        日付,内容,お支払金額,お預り金額,残高
        2026/06/30,振込 カ）サンプル,,11000,111000
        2026/06/29,引落 デンキ,3000,,108000
        CSV;

        $result = BankCsvParser::parse($csv, BankImportPresets::netBankCreditDebit());

        self::assertNull($result->formatError);
        self::assertCount(2, $result->transactions);
        self::assertSame(BankTransactionDirection::Credit, $result->transactions[0]->direction);
        self::assertSame(11000, $result->transactions[0]->amountCents);
        self::assertSame('振込 カ）サンプル', $result->transactions[0]->payerName);
        self::assertSame(BankTransactionDirection::Debit, $result->transactions[1]->direction);
        self::assertSame(3000, $result->transactions[1]->amountCents);
    }

    public function test_shift_jis_is_decoded(): void
    {
        $utf8 = "日付,金額,依頼人,摘要\n2026-06-30,5000,カ）ネネショウカイ,振込\n";
        $sjis = mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8');

        $result = BankCsvParser::parse($sjis, BankImportPresets::signedAmount());

        self::assertNull($result->formatError);
        self::assertCount(1, $result->transactions);
        self::assertSame('カ）ネネショウカイ', $result->transactions[0]->payerName);
        self::assertSame(5000, $result->transactions[0]->amountCents);
    }

    public function test_unparseable_date_becomes_a_row_error_others_still_parse(): void
    {
        $csv = <<<CSV
        日付,金額,依頼人,摘要
        2026-06-30,1000,カ）A,振込
        not-a-date,2000,カ）B,振込
        CSV;

        $result = BankCsvParser::parse($csv, BankImportPresets::signedAmount());

        self::assertNull($result->formatError);
        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]['line']);
    }

    public function test_mapping_without_amount_source_is_rejected(): void
    {
        $result = BankCsvParser::parse("日付\n2026-06-30\n", new BankCsvColumnMapping(valueDateColumn: 0));

        self::assertNotNull($result->formatError);
        self::assertSame([], $result->transactions);
    }

    public function test_bank_reference_column_is_captured(): void
    {
        $csv = <<<CSV
        日付,金額,依頼人,摘要,取引ID
        2026-06-30,7000,カ）A,振込,TX-001
        CSV;

        $mapping = new BankCsvColumnMapping(
            valueDateColumn: 0,
            dateFormat: 'Y-m-d',
            amountColumn: 1,
            payerNameColumn: 2,
            descriptionColumn: 3,
            bankReferenceColumn: 4,
        );

        $result = BankCsvParser::parse($csv, $mapping);

        self::assertCount(1, $result->transactions);
        self::assertSame('TX-001', $result->transactions[0]->bankReference);
    }
}
