import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { BankMatchSuggestionListDto, BankTransactionListDto } from './api-types'
import type { BankTransactionId } from './ids'
import { toBankMatchSuggestion, toBankTransactionPage } from './mapper'
import type { BankMatchSuggestion, BankTransactionPage } from './model'
import { bankTransactionKeys, type BankTransactionListParams } from './query-keys'

/** GET /admin/bank-transactions — staged lines, mapped to models before the cache. */
export function useBankTransactionList(
  params: BankTransactionListParams,
): UseQueryResult<BankTransactionPage, AppError> {
  return useQuery<BankTransactionPage, AppError>({
    queryKey: bankTransactionKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams()
      if (params.status !== null) search.set('status', params.status)
      search.set('limit', String(params.limit))
      search.set('offset', String(params.offset))
      const dto = await apiClient.get<BankTransactionListDto>(
        `/admin/bank-transactions?${search.toString()}`,
      )
      return toBankTransactionPage(dto)
    },
  })
}

/**
 * GET /admin/bank-transactions/{id}/suggestions — scored invoice candidates for a
 * staged deposit. Disabled until a transaction is selected (`id` null).
 */
export function useBankTransactionSuggestions(
  id: BankTransactionId | null,
): UseQueryResult<BankMatchSuggestion[], AppError> {
  return useQuery<BankMatchSuggestion[], AppError>({
    queryKey: bankTransactionKeys.suggestions(id ?? 0),
    enabled: id !== null,
    queryFn: async () => {
      const dto = await apiClient.get<BankMatchSuggestionListDto>(
        `/admin/bank-transactions/${String(id)}/suggestions`,
      )
      return dto.items.map(toBankMatchSuggestion)
    },
  })
}
