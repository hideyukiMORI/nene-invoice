import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { OrganizationDto, OrganizationListDto } from './api-types'
import type { OrganizationId } from './ids'
import { toOrganization, toOrganizationPage } from './mapper'
import type { Organization, OrganizationPage } from './model'
import { organizationKeys, type OrganizationListParams } from './query-keys'

/** GET /admin/organizations — list page (superadmin only). */
export function useOrganizationList(
  params: OrganizationListParams,
): UseQueryResult<OrganizationPage, AppError> {
  return useQuery<OrganizationPage, AppError>({
    queryKey: organizationKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const dto = await apiClient.get<OrganizationListDto>(
        `/admin/organizations?${search.toString()}`,
      )
      return toOrganizationPage(dto)
    },
  })
}

/** GET /admin/organizations/{id} — one organization. */
export function useOrganization(id: OrganizationId): UseQueryResult<Organization, AppError> {
  return useQuery<Organization, AppError>({
    queryKey: organizationKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<OrganizationDto>(`/admin/organizations/${String(id)}`)
      return toOrganization(dto)
    },
  })
}
