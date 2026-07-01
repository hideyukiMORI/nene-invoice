import type { OrganizationId } from './ids'

/** UI read model for an organization (tenant). Field names mirror the API. */
export interface Organization {
  id: OrganizationId
  name: string
  slug: string
  plan: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface OrganizationPage {
  items: Organization[]
  total: number
  limit: number
  offset: number
}

/**
 * Create input. `adminEmail` / `adminPassword` are optional and both-or-neither:
 * when supplied, the tenant's first admin is provisioned with the organization.
 */
export interface CreateOrganizationInput {
  name: string
  slug: string
  plan?: string
  adminEmail?: string
  adminPassword?: string
}
