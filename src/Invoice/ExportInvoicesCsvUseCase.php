<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for all issued invoices in the organization.
 * UTF-8 BOM is prepended so Excel opens the file without encoding issues.
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

    public function execute(): string
    {
        $rows = $this->invoices->findIssuedForExport();

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
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

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['invoice_number'],
                $row['issued_at'] !== null ? Jst::date($row['issued_at']) : '',
                $row['due_at'],
                $row['client_name'],
                $row['subtotal_cents'],
                $row['tax_cents'],
                $row['total_cents'],
                self::STATUS_LABELS[$row['status']] ?? $row['status'],
                $row['is_qualified_invoice'] ? 'はい' : 'いいえ',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
