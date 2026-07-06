<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Export\CsvWriter;
use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for the quotes matching the given admin filter (so the
 * export mirrors the list). {@see CsvWriter} defaults add the Excel-safe UTF-8
 * BOM, neutralize formula injection, and quote per RFC 4180.
 */
final readonly class ExportQuotesCsvUseCase implements ExportQuotesCsvUseCaseInterface
{
    private const STATUS_LABELS = [
        'draft'    => '下書き',
        'sent'     => '送付済み',
        'accepted' => '承認済み',
        'rejected' => '却下',
        'expired'  => '期限切れ',
    ];

    public function __construct(private QuoteRepositoryInterface $quotes)
    {
    }

    public function execute(QuoteListFilter $filter): string
    {
        $rows = $this->quotes->findForExport($filter);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        $writer = new CsvWriter($handle, [
            '見積番号',
            '発行日',
            '有効期限',
            '取引先',
            '小計(円)',
            '消費税(円)',
            '合計(円)',
            'ステータス',
        ]);

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                $row['quote_number'],
                $row['issued_at'] !== null && $row['issued_at'] !== '' ? Jst::date($row['issued_at']) : '',
                $row['valid_until'],
                $row['client_name'],
                $row['subtotal_cents'],
                $row['tax_cents'],
                $row['total_cents'],
                self::STATUS_LABELS[$row['status']] ?? $row['status'],
            ];
        }

        // writeAll emits the BOM + header even when there are no data rows.
        $writer->writeAll($data);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
