import type { OrganizationId } from './ids'

export interface OrganizationListParams {
  limit: number
  offset: number
}

export const organizationKeys = {
  all: ['organizations'] as const,
  lists: () => [...organizationKeys.all, 'list'] as const,
  list: (params: OrganizationListParams) => [...organizationKeys.lists(), params] as const,
  details: () => [...organizationKeys.all, 'detail'] as const,
  detail: (id: OrganizationId) => [...organizationKeys.details(), id] as const,
}
