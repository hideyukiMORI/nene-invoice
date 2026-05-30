import { describe, expect, it } from 'vitest'
import type { CurrentUserDto } from './api-types'
import { toCurrentUser } from './mapper'

describe('toCurrentUser', () => {
  it('maps an org-scoped user', () => {
    const dto: CurrentUserDto = {
      id: 7,
      email: 'admin@example.com',
      role: 'admin',
      organization_id: 1,
    }
    const user = toCurrentUser(dto)
    expect(user.id).toBe(7)
    expect(user.email).toBe('admin@example.com')
    expect(user.role).toBe('admin')
    expect(user.organization_id).toBe(1)
  })

  it('treats a superadmin (no organization) as null org', () => {
    const dto: CurrentUserDto = {
      id: 1,
      email: 'root@example.com',
      role: 'superadmin',
    }
    expect(toCurrentUser(dto).organization_id).toBeNull()
  })
})
