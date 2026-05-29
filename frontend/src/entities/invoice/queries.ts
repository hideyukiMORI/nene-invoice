import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { InvoiceListDto } from './api-types'
import { toInvoicePage } from './mapper'
import type { InvoicePage } from './model'
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
