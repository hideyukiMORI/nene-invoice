<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Export\CsvWriter;

/**
 * Assembles CSV bytes for the clients matching the given admin filter (so the
 * export mirrors the list). Cells are written via {@see CsvWriter}, whose
 * defaults prepend a UTF-8 BOM (Excel-safe), neutralize spreadsheet formula
 * injection (client names / addresses are user input), and quote per RFC 4180.
 */
final readonly class ExportClientsCsvUseCase implements ExportClientsCsvUseCaseInterface
{
    public function __construct(private ClientRepositoryInterface $clients)
    {
    }

    public function execute(ClientListFilter $filter): string
    {
        $rows = $this->clients->findForExport($filter);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        $writer = new CsvWriter($handle, [
            '取引先名',
            'カナ',
            '担当者',
            'メール',
            '請求先住所',
            '登録番号',
        ]);

        $data = [];

        foreach ($rows as $client) {
            $data[] = [
                $client->name,
                $client->nameKana ?? '',
                $client->contactName ?? '',
                $client->email ?? '',
                $client->billingAddress ?? '',
                $client->registrationNumber ?? '',
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
