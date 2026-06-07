<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use NeneInvoice\Support\Csv;

/**
 * Assembles CSV bytes for the items matching the given admin filter. The columns
 * are the {@see ItemImportTemplate} shape so an export round-trips into an
 * import (edit in Excel → re-import). `標準単価` is whole yen (cents 1:1 for JPY);
 * `標準税率` is a percent (1000 bps → "10"). UTF-8 BOM for Excel; cells written
 * via {@see Csv} to neutralize formula injection.
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

        fwrite($handle, "\xEF\xBB\xBF");
        Csv::putRow($handle, ItemImportTemplate::HEADER);

        foreach ($rows as $item) {
            Csv::putRow($handle, [
                ItemImportTemplate::VERSION,
                (string) $item->id,
                $item->description,
                (string) $item->defaultUnitPriceCents,
                (string) intdiv($item->defaultTaxRateBps, 100),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
