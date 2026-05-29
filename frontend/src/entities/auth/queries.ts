import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CurrentUserDto } from './api-types'
import { toCurrentUser } from './mapper'
import type { CurrentUser } from './model'
import { authKeys } from './query-keys'

/**
 * GET /admin/me — the authenticated user. `enabled` gates it on a present token so
 * the auth shell does not fire an inevitable 401. Session data does not go stale.
 */
export function useCurrentUser(enabled: boolean): UseQueryResult<CurrentUser, AppError> {
  return useQuery<CurrentUser, AppError>({
    queryKey: authKeys.currentUser(),
    enabled,
    retry: false,
    staleTime: Infinity,
    queryFn: async () => {
      const dto = await apiClient.get<CurrentUserDto>('/admin/me')
      return toCurrentUser(dto)
    },
  })
}
