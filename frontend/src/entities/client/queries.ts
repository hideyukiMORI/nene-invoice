import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ClientListDto } from './api-types'
import { toClientPage } from './mapper'
import type { ClientPage } from './model'
import { clientKeys, type ClientListParams } from './query-keys'

/** GET /admin/clients — list page, mapped to models before reaching the cache. */
export function useClientList(params: ClientListParams): UseQueryResult<ClientPage, AppError> {
  return useQuery<ClientPage, AppError>({
    queryKey: clientKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const dto = await apiClient.get<ClientListDto>(`/admin/clients?${search.toString()}`)
      return toClientPage(dto)
    },
  })
}
