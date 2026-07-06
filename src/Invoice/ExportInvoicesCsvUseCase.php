<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Export\CsvWriter;
use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for all issued invoices in the organization. {@see CsvWriter}
 * defaults add the Excel-safe UTF-8 BOM, neutralize formula injection, and quote
 * per RFC 4180.
 */
final readonly class ExportInvoicesCsvUseCase implements ExportInvoicesCsvUseCaseInterface
{
    private const STATUS_LABELS = [
        'issued'         => '発行済み',
        'partially_paid' => '一部入金',
        'paid'           => '入金済み',
    ];

    public function __construct(private InvoiceRepositoryInterface $invoices)
    {
    }

    public function execute(InvoiceListFilter $filter): string
    {
        $rows = $this->invoices->findIssuedForExport($filter);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        $writer = new CsvWriter($handle, [
            '請求書番号',
            '発行日',
            '支払期限',
            '取引先',
            '小計(円)',
            '消費税(円)',
            '合計(円)',
            'ステータス',
            '適格請求書',
        ]);

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                $row['invoice_number'],
                $row['issued_at'] !== null ? Jst::date($row['issued_at']) : '',
                $row['due_at'],
                $row['client_name'],
                $row['subtotal_cents'],
                $row['tax_cents'],
                $row['total_cents'],
                self::STATUS_LABELS[$row['status']] ?? $row['status'],
                $row['is_qualified_invoice'] ? 'はい' : 'いいえ',
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
