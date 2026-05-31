import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { UserListDto, UserDto } from './api-types'
import type { UserId } from './ids'
import { toUser, toUserPage } from './mapper'
import type { User, UserPage } from './model'
import { userKeys, type UserListParams } from './query-keys'

/** GET /admin/users — list page. */
export function useUserList(params: UserListParams): UseQueryResult<UserPage, AppError> {
  return useQuery<UserPage, AppError>({
    queryKey: userKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const dto = await apiClient.get<UserListDto>(`/admin/users?${search.toString()}`)
      return toUserPage(dto)
    },
  })
}

/** GET /admin/users/{id} — one user. */
export function useUser(id: UserId): UseQueryResult<User, AppError> {
  return useQuery<User, AppError>({
    queryKey: userKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<UserDto>(`/admin/users/${String(id)}`)
      return toUser(dto)
    },
  })
}
