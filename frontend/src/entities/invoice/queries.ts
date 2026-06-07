import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { InvoiceListDto, InvoiceWithLinesDto } from './api-types'
import type { InvoiceId } from './ids'
import { toInvoicePage, toInvoiceWithLines } from './mapper'
import type { InvoiceListFilters, InvoicePage, InvoiceSort, InvoiceWithLines } from './model'
import { invoiceKeys, type InvoiceListParams } from './query-keys'

/**
 * Serializes the admin list filters + sort into query params. Shared by the
 * list query and the CSV export so the export mirrors exactly what the list
 * shows (search / status / date ranges / amount / sort).
 */
export function buildInvoiceListSearch(
  filters: InvoiceListFilters,
  sort: InvoiceSort,
): URLSearchParams {
  const search = new URLSearchParams()
  if (filters.q !== null) search.set('q', filters.q)
  if (filters.statuses.length > 0) search.set('status', filters.statuses.join(','))
  if (filters.overdue) search.set('overdue', '1')
  if (filters.due_from !== null) search.set('due_from', filters.due_from)
  if (filters.due_to !== null) search.set('due_to', filters.due_to)
  if (filters.issued_from !== null) search.set('issued_from', filters.issued_from)
  if (filters.issued_to !== null) search.set('issued_to', filters.issued_to)
  if (filters.total_min !== null) search.set('total_min', String(filters.total_min))
  if (filters.total_max !== null) search.set('total_max', String(filters.total_max))
  if (sort.field !== null) {
    search.set('sort', sort.field)
    search.set('order', sort.order)
  }
  return search
}

/** GET /admin/invoices — list page, mapped to models before reaching the cache. */
export function useInvoiceList(params: InvoiceListParams): UseQueryResult<InvoicePage, AppError> {
  return useQuery<InvoicePage, AppError>({
    queryKey: invoiceKeys.list(params),
    queryFn: async () => {
      const search = buildInvoiceListSearch(params.filters, params.sort)
      search.set('limit', String(params.limit))
      search.set('offset', String(params.offset))
      const dto = await apiClient.get<InvoiceListDto>(`/admin/invoices?${search.toString()}`)
      return toInvoicePage(dto)
    },
  })
}

/** GET /admin/invoices/{id} — one invoice with its line items. */
export function useInvoice(id: InvoiceId): UseQueryResult<InvoiceWithLines, AppError> {
  return useQuery<InvoiceWithLines, AppError>({
    queryKey: invoiceKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<InvoiceWithLinesDto>(`/admin/invoices/${String(id)}`)
      return toInvoiceWithLines(dto)
    },
  })
}
