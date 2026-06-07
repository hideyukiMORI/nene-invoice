import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import type { InvoiceListFilters, InvoiceSort } from './model'
import { buildInvoiceListSearch } from './queries'

/** Downloads the issued invoices matching the current list filters as CSV. */
export function useExportInvoicesCsv(filters: InvoiceListFilters, sort: InvoiceSort): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  const qs = buildInvoiceListSearch(filters, sort).toString()
  const path = qs === '' ? '/admin/invoices/export' : `/admin/invoices/export?${qs}`
  return useExportCsvBase(path, `invoices-${today}.csv`, 'admin.invoices.export.error')
}

/** Downloads all payments as CSV. */
export function useExportPaymentsCsv(): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  return useExportCsvBase(
    '/admin/payments/export',
    `payments-${today}.csv`,
    'admin.invoices.export.paymentsError',
  )
}
