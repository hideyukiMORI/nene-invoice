<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Export\CsvWriter;

/**
 * Assembles CSV bytes for the items matching the given admin filter. The columns
 * are the {@see ItemImportTemplate} shape so an export round-trips into an
 * import (edit in Excel → re-import). `標準単価` is whole yen (cents 1:1 for JPY);
 * `標準税率` is a percent (1000 bps → "10"). Cells are written via {@see CsvWriter},
 * whose defaults add the Excel-safe BOM, neutralize formula injection, and quote
 * per RFC 4180.
 */
final readonly class ExportItemsCsvUseCase implements ExportItemsCsvUseCaseInterface
{
    public function __construct(private ItemRepositoryInterface $items)
    {
    }

    public function execute(ItemListFilter $filter): string
    {
        $rows = $this->items->findForExport($filter);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        $writer = new CsvWriter($handle, ItemImportTemplate::HEADER);

        $data = [];

        foreach ($rows as $item) {
            $data[] = [
                ItemImportTemplate::VERSION,
                (string) $item->id,
                $item->description,
                (string) $item->defaultUnitPriceCents,
                (string) intdiv($item->defaultTaxRateBps, 100),
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
