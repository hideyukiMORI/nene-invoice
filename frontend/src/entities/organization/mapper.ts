import type { OrganizationDto, OrganizationListDto } from './api-types'
import { toOrganizationId } from './ids'
import type { Organization, OrganizationPage } from './model'

export function toOrganization(dto: OrganizationDto): Organization {
  return {
    id: toOrganizationId(dto.id),
    name: dto.name,
    slug: dto.slug,
    plan: dto.plan ?? null,
    is_active: dto.is_active,
    created_at: dto.created_at ?? null,
    updated_at: dto.updated_at ?? null,
  }
}

export function toOrganizationPage(dto: OrganizationListDto): OrganizationPage {
  return {
    items: dto.items.map(toOrganization),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
