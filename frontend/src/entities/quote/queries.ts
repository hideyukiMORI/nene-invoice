import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { QuoteListDto, QuoteWithLinesDto } from './api-types'
import { toQuotePage, toQuoteWithLines } from './mapper'
import type { QuotePage, QuoteWithLines } from './model'
import { quoteKeys } from './query-keys'
import type { QuoteId } from './ids'

export function useQuoteList(params: {
  limit: number
  offset: number
}): UseQueryResult<QuotePage, AppError> {
  return useQuery<QuotePage, AppError>({
    queryKey: quoteKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
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
