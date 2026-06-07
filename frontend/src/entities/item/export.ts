import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import type { ItemListFilters, ItemSort } from './model'
import { buildItemListSearch } from './queries'

/** Downloads the items matching the current list filter as CSV. */
export function useExportItemsCsv(filters: ItemListFilters, sort: ItemSort): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  const qs = buildItemListSearch(filters, sort).toString()
  const path = qs === '' ? '/admin/items/export' : `/admin/items/export?${qs}`
  return useExportCsvBase(path, `items-${today}.csv`, 'admin.items.export.error')
}
