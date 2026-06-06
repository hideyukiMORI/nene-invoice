<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use NeneInvoice\Support\Jst;

/**
 * Assembles CSV bytes for all valid (non-voided) payments in the organization.
 * UTF-8 BOM is prepended so Excel opens the file without encoding issues.
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

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '請求書番号',
            '取引先',
            '入金日',
            '金額(円)',
            '方法',
            '備考',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['invoice_number'],
                $row['client_name'],
                Jst::date($row['paid_at']),
                $row['amount_cents'],
                self::METHOD_LABELS[$row['method']] ?? $row['method'],
                $row['note'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
