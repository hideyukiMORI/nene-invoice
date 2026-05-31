import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { UserDto } from './api-types'
import type { UserId } from './ids'
import { toUser } from './mapper'
import type { CreateUserInput, UpdateUserInput, User } from './model'
import { userKeys } from './query-keys'

/** POST /admin/users — creates a user; invalidates the user list on success. */
export function useCreateUser(): UseMutationResult<User, AppError, CreateUserInput> {
  const queryClient = useQueryClient()

  return useMutation<User, AppError, CreateUserInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<UserDto>('/admin/users', {
        email: input.email,
        password: input.password,
        role: input.role,
      })
      return toUser(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: userKeys.lists() })
    },
  })
}

/** PATCH /admin/users/{id} — updates a user; invalidates lists and detail. */
export function useUpdateUser(): UseMutationResult<User, AppError, UpdateUserInput> {
  const queryClient = useQueryClient()

  return useMutation<User, AppError, UpdateUserInput>({
    mutationFn: async (input) => {
      const body: Record<string, unknown> = {}
      if (input.email !== undefined) body['email'] = input.email
      if (input.password !== undefined && input.password !== '') body['password'] = input.password
      if (input.role !== undefined) body['role'] = input.role
      const dto = await apiClient.patch<UserDto>(`/admin/users/${String(input.id)}`, body)
      return toUser(dto)
    },
    onSuccess: (user) => {
      void queryClient.invalidateQueries({ queryKey: userKeys.lists() })
      void queryClient.invalidateQueries({ queryKey: userKeys.detail(user.id) })
    },
  })
}

/** DELETE /admin/users/{id} — deletes a user; invalidates the lists. */
export function useDeleteUser(): UseMutationResult<UserId, AppError, UserId> {
  const queryClient = useQueryClient()

  return useMutation<UserId, AppError, UserId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/users/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: userKeys.lists() })
    },
  })
}
