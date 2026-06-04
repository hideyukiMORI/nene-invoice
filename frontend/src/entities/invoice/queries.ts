import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { InvoiceListDto, InvoiceWithLinesDto } from './api-types'
import type { InvoiceId } from './ids'
import { toInvoicePage, toInvoiceWithLines } from './mapper'
import type { InvoicePage, InvoiceWithLines } from './model'
import { invoiceKeys, type InvoiceListParams } from './query-keys'

/** GET /admin/invoices — list page, mapped to models before reaching the cache. */
export function useInvoiceList(params: InvoiceListParams): UseQueryResult<InvoicePage, AppError> {
  return useQuery<InvoicePage, AppError>({
    queryKey: invoiceKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const { filters, sort } = params
      if (filters.q !== null) search.set('q', filters.q)
      if (filters.statuses.length > 0) search.set('status', filters.statuses.join(','))
      if (filters.overdue) search.set('overdue', '1')
      if (filters.due_from !== null) search.set('due_from', filters.due_from)
      if (filters.due_to !== null) search.set('due_to', filters.due_to)
      if (filters.total_min !== null) search.set('total_min', String(filters.total_min))
      if (filters.total_max !== null) search.set('total_max', String(filters.total_max))
      if (sort.field !== null) {
        search.set('sort', sort.field)
        search.set('order', sort.order)
      }
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
