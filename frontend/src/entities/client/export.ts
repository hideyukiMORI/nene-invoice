import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import type { ClientListFilters, ClientSort } from './model'
import { buildClientListSearch } from './queries'

/** Downloads the clients matching the current list filter as CSV. */
export function useExportClientsCsv(filters: ClientListFilters, sort: ClientSort): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  const qs = buildClientListSearch(filters, sort).toString()
  const path = qs === '' ? '/admin/clients/export' : `/admin/clients/export?${qs}`
  return useExportCsvBase(path, `clients-${today}.csv`, 'admin.clients.export.error')
}
