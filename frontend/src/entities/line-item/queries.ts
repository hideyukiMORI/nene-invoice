import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { LineItemSuggestionListDto } from './api-types'
import { toLineItemSuggestion } from './mapper'
import type { LineItemSuggestion } from './model'
import { lineItemKeys } from './query-keys'

/**
 * GET /admin/line-items/suggestions — history-based suggestions, mapped to
 * models before reaching the cache. The full set is fetched once and filtered
 * client-side by the suggest input (same approach as the client picker).
 */
export function useLineItemSuggestions(): UseQueryResult<LineItemSuggestion[], AppError> {
  return useQuery<LineItemSuggestion[], AppError>({
    queryKey: lineItemKeys.suggestions(),
    queryFn: async () => {
      const dto = await apiClient.get<LineItemSuggestionListDto>('/admin/line-items/suggestions')
      return dto.items.map(toLineItemSuggestion)
    },
  })
}
