import { describe, expect, it } from 'vitest'
import type { UserDto } from './api-types'
import { toUser, toUserPage } from './mapper'

const dto: UserDto = {
  id: 7,
  email: 'admin@example.com',
  role: 'admin',
  organization_id: 1,
  status: 'active',
  created_at: '2026-05-01 00:00:00',
  updated_at: '2026-05-01 00:00:00',
}

describe('toUser', () => {
  it('brands the id and maps all fields', () => {
    const user = toUser(dto)
    expect(user.id).toBe(7)
    expect(user.email).toBe('admin@example.com')
    expect(user.role).toBe('admin')
    expect(user.organization_id).toBe(1)
    expect(user.status).toBe('active')
  })

  it('defaults status to active when missing', () => {
    // Delete the optional key entirely (rather than set it to `undefined`) to
    // satisfy exactOptionalPropertyTypes while exercising the same "absent" case.
    const missingStatus = { ...dto }
    delete missingStatus.status
    const user = toUser(missingStatus)
    expect(user.status).toBe('active')
  })

  it('normalises nullable organization_id', () => {
    const user = toUser({ ...dto, organization_id: null })
    expect(user.organization_id).toBeNull()
  })

  it('normalises nullable timestamps', () => {
    const user = toUser({ ...dto, created_at: null, updated_at: null })
    expect(user.created_at).toBeNull()
    expect(user.updated_at).toBeNull()
  })
})

describe('toUserPage', () => {
  it('maps items and pagination', () => {
    const page = toUserPage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(7)
    expect(page.total).toBe(1)
    expect(page.limit).toBe(100)
    expect(page.offset).toBe(0)
  })

  it('maps an empty page', () => {
    const page = toUserPage({ items: [], total: 0, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(0)
    expect(page.total).toBe(0)
  })
})
