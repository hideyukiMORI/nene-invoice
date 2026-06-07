<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\Support\Csv;
use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for the quotes matching the given admin filter (so the
 * export mirrors the list). UTF-8 BOM is prepended so Excel opens the file
 * without encoding issues.
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

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        Csv::putRow($handle, [
            '見積番号',
            '発行日',
            '有効期限',
            '取引先',
            '小計(円)',
            '消費税(円)',
            '合計(円)',
            'ステータス',
        ]);

        foreach ($rows as $row) {
            Csv::putRow($handle, [
                $row['quote_number'],
                $row['issued_at'] !== null && $row['issued_at'] !== '' ? Jst::date($row['issued_at']) : '',
                $row['valid_until'],
                $row['client_name'],
                $row['subtotal_cents'],
                $row['tax_cents'],
                $row['total_cents'],
                self::STATUS_LABELS[$row['status']] ?? $row['status'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
