<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Export\CsvWriter;
use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for all valid (non-voided) payments in the organization.
 * {@see CsvWriter} defaults add the Excel-safe UTF-8 BOM, neutralize formula
 * injection, and quote per RFC 4180.
 */
final readonly class ExportPaymentsCsvUseCase implements ExportPaymentsCsvUseCaseInterface
{
    private const METHOD_LABELS = [
        'bank_transfer' => '銀行振込',
        'cash'          => '現金',
        'other'         => 'その他',
    ];

    public function __construct(private PaymentRepositoryInterface $payments)
    {
    }

    public function execute(): string
    {
        $rows = $this->payments->findValidForExport();

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        $writer = new CsvWriter($handle, [
            '請求書番号',
            '取引先',
            '入金日',
            '金額(円)',
            '方法',
            '備考',
        ]);

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                $row['invoice_number'],
                $row['client_name'],
                Jst::date($row['paid_at']),
                $row['amount_cents'],
                self::METHOD_LABELS[$row['method']] ?? $row['method'],
                $row['note'],
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
