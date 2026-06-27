import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { RecurringInvoiceListDto, RecurringInvoiceWithLinesDto } from './api-types'
import type { RecurringInvoiceId } from './ids'
import { toRecurringInvoicePage, toRecurringInvoiceWithLines } from './mapper'
import type { RecurringInvoicePage, RecurringInvoiceWithLines } from './model'
import { recurringInvoiceKeys, type RecurringInvoiceListParams } from './query-keys'

/** GET /admin/recurring-invoices — list page, mapped to models before reaching the cache. */
export function useRecurringInvoiceList(
  params: RecurringInvoiceListParams,
): UseQueryResult<RecurringInvoicePage, AppError> {
  return useQuery<RecurringInvoicePage, AppError>({
    queryKey: recurringInvoiceKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams()
      search.set('limit', String(params.limit))
      search.set('offset', String(params.offset))
      const dto = await apiClient.get<RecurringInvoiceListDto>(
        `/admin/recurring-invoices?${search.toString()}`,
      )
      return toRecurringInvoicePage(dto)
    },
  })
}

/** GET /admin/recurring-invoices/{id} — one schedule with its line template. */
export function useRecurringInvoice(
  id: RecurringInvoiceId,
): UseQueryResult<RecurringInvoiceWithLines, AppError> {
  return useQuery<RecurringInvoiceWithLines, AppError>({
    queryKey: recurringInvoiceKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<RecurringInvoiceWithLinesDto>(
        `/admin/recurring-invoices/${String(id)}`,
      )
      return toRecurringInvoiceWithLines(dto)
    },
  })
}
