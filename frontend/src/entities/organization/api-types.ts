import type { components } from '@/shared/api/schema.gen'

export type OrganizationDto = components['schemas']['Organization']
export type CreateOrganizationRequestDto = components['schemas']['CreateOrganizationRequest']

export interface OrganizationListDto {
  items: OrganizationDto[]
  total: number
  limit: number
  offset: number
}
