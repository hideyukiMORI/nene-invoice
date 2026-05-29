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
