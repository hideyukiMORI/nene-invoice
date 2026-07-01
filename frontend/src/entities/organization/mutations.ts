import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { OrganizationDto } from './api-types'
import type { OrganizationId } from './ids'
import { toOrganization } from './mapper'
import type { CreateOrganizationInput, Organization } from './model'
import { organizationKeys } from './query-keys'

/**
 * POST /admin/organizations — creates a tenant. When both `adminEmail` and
 * `adminPassword` are present they are sent so the API provisions the tenant's
 * first admin atomically; otherwise only the org is created. Invalidates the
 * organization list on success.
 */
export function useCreateOrganization(): UseMutationResult<
  Organization,
  AppError,
  CreateOrganizationInput
> {
  const queryClient = useQueryClient()

  return useMutation<Organization, AppError, CreateOrganizationInput>({
    mutationFn: async (input) => {
      const body: Record<string, unknown> = {
        name: input.name,
        slug: input.slug,
      }
      if (input.plan !== undefined && input.plan !== '') body['plan'] = input.plan
      if (input.adminEmail !== undefined && input.adminPassword !== undefined) {
        body['admin_email'] = input.adminEmail
        body['admin_password'] = input.adminPassword
      }
      const dto = await apiClient.post<OrganizationDto>('/admin/organizations', body)
      return toOrganization(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: organizationKeys.lists() })
    },
  })
}

/** DELETE /admin/organizations/{id} — deletes a tenant; invalidates the lists. */
export function useDeleteOrganization(): UseMutationResult<
  OrganizationId,
  AppError,
  OrganizationId
> {
  const queryClient = useQueryClient()

  return useMutation<OrganizationId, AppError, OrganizationId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/organizations/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: organizationKeys.lists() })
    },
  })
}
