import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import type { QuoteListFilters, QuoteSort } from './model'
import { buildQuoteListSearch } from './queries'

/** Downloads the quotes matching the current list filters as CSV. */
export function useExportQuotesCsv(filters: QuoteListFilters, sort: QuoteSort): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  const qs = buildQuoteListSearch(filters, sort).toString()
  const path = qs === '' ? '/admin/quotes/export' : `/admin/quotes/export?${qs}`
  return useExportCsvBase(path, `quotes-${today}.csv`, 'admin.quotes.export.error')
}
