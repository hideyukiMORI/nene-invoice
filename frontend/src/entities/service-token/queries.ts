import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ServiceTokenListDto } from './api-types'
import { toServiceTokenPage } from './mapper'
import type { ServiceTokenPage } from './model'
import { serviceTokenKeys, type ServiceTokenListParams } from './query-keys'

/** GET /admin/service-tokens — registry list page (metadata only). */
export function useServiceTokenList(
  params: ServiceTokenListParams,
): UseQueryResult<ServiceTokenPage, AppError> {
  return useQuery<ServiceTokenPage, AppError>({
    queryKey: serviceTokenKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const dto = await apiClient.get<ServiceTokenListDto>(
        `/admin/service-tokens?${search.toString()}`,
      )
      return toServiceTokenPage(dto)
    },
  })
}
