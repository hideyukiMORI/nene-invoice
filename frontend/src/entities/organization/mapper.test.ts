import { describe, expect, it } from 'vitest'
import type { OrganizationDto } from './api-types'
import { toOrganization, toOrganizationPage } from './mapper'

const dto: OrganizationDto = {
  id: 3,
  name: 'Acme KK',
  slug: 'acme',
  plan: 'free',
  is_active: true,
  external_id: null,
  custom_domain: null,
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('toOrganization', () => {
  it('brands the id and maps all fields', () => {
    const org = toOrganization(dto)
    expect(org.id).toBe(3)
    expect(org.name).toBe('Acme KK')
    expect(org.slug).toBe('acme')
    expect(org.plan).toBe('free')
    expect(org.is_active).toBe(true)
  })

  it('normalises nullable plan and timestamps', () => {
    const org = toOrganization({ ...dto, plan: null, created_at: null, updated_at: null })
    expect(org.plan).toBeNull()
    expect(org.created_at).toBeNull()
    expect(org.updated_at).toBeNull()
  })
})

describe('toOrganizationPage', () => {
  it('maps items and pagination', () => {
    const page = toOrganizationPage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(3)
    expect(page.total).toBe(1)
    expect(page.limit).toBe(100)
    expect(page.offset).toBe(0)
  })

  it('maps an empty page', () => {
    const page = toOrganizationPage({ items: [], total: 0, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(0)
    expect(page.total).toBe(0)
  })
})
