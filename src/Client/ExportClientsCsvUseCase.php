<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use NeneInvoice\Support\Csv;

/**
 * Assembles CSV bytes for the clients matching the given admin filter (so the
 * export mirrors the list). UTF-8 BOM is prepended so Excel opens the file
 * without encoding issues; cells are written via {@see Csv} to neutralize
 * spreadsheet formula injection (client names / addresses are user input).
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

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        Csv::putRow($handle, [
            '取引先名',
            'カナ',
            '担当者',
            'メール',
            '請求先住所',
            '登録番号',
        ]);

        foreach ($rows as $client) {
            Csv::putRow($handle, [
                $client->name,
                $client->nameKana ?? '',
                $client->contactName ?? '',
                $client->email ?? '',
                $client->billingAddress ?? '',
                $client->registrationNumber ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
