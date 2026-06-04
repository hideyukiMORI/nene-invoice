import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { QuoteListDto, QuoteWithLinesDto } from './api-types'
import { toQuotePage, toQuoteWithLines } from './mapper'
import type { QuotePage, QuoteWithLines } from './model'
import { quoteKeys, type QuoteListParams } from './query-keys'
import type { QuoteId } from './ids'

export function useQuoteList(params: QuoteListParams): UseQueryResult<QuotePage, AppError> {
  return useQuery<QuotePage, AppError>({
    queryKey: quoteKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const { filters, sort } = params
      if (filters.q !== null) search.set('q', filters.q)
      if (filters.statuses.length > 0) search.set('status', filters.statuses.join(','))
      if (filters.valid_from !== null) search.set('valid_from', filters.valid_from)
      if (filters.valid_to !== null) search.set('valid_to', filters.valid_to)
      if (filters.total_min !== null) search.set('total_min', String(filters.total_min))
      if (filters.total_max !== null) search.set('total_max', String(filters.total_max))
      if (sort.field !== null) {
        search.set('sort', sort.field)
        search.set('order', sort.order)
      }
      const dto = await apiClient.get<QuoteListDto>(`/admin/quotes?${search.toString()}`)
      return toQuotePage(dto)
    },
  })
}

export function useQuote(id: QuoteId): UseQueryResult<QuoteWithLines, AppError> {
  return useQuery<QuoteWithLines, AppError>({
    queryKey: quoteKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<QuoteWithLinesDto>(`/admin/quotes/${String(id)}`)
      return toQuoteWithLines(dto)
    },
  })
}
