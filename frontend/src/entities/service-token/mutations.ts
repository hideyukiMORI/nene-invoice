import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CreatedServiceTokenDto } from './api-types'
import type { ServiceTokenId } from './ids'
import { toIssuedServiceToken } from './mapper'
import type { IssuedServiceToken, IssueServiceTokenInput } from './model'
import { serviceTokenKeys } from './query-keys'

/**
 * POST /admin/service-tokens — issues a token and returns the one-time plaintext
 * value; invalidates the registry list on success.
 */
export function useIssueServiceToken(): UseMutationResult<
  IssuedServiceToken,
  AppError,
  IssueServiceTokenInput
> {
  const queryClient = useQueryClient()

  return useMutation<IssuedServiceToken, AppError, IssueServiceTokenInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<CreatedServiceTokenDto>('/admin/service-tokens', {
        label: input.label,
        scopes: input.scopes,
      })
      return toIssuedServiceToken(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: serviceTokenKeys.lists() })
    },
  })
}

/** DELETE /admin/service-tokens/{id} — revokes a token; invalidates the list. */
export function useRevokeServiceToken(): UseMutationResult<
  ServiceTokenId,
  AppError,
  ServiceTokenId
> {
  const queryClient = useQueryClient()

  return useMutation<ServiceTokenId, AppError, ServiceTokenId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/service-tokens/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: serviceTokenKeys.lists() })
    },
  })
}
