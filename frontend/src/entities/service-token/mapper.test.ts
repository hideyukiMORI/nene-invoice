import { describe, expect, it } from 'vitest'
import type { CreatedServiceTokenDto, ServiceTokenDto } from './api-types'
import { toIssuedServiceToken, toServiceToken, toServiceTokenPage } from './mapper'

const dto: ServiceTokenDto = {
  id: 5,
  subject: 'service:clear',
  label: 'NeNe Clear',
  scopes: ['read:invoices', 'write:payments'],
  created_by: 7,
  created_at: '2026-06-09 00:00:00',
  expires_at: '2026-07-09 00:00:00',
  revoked_at: null,
  status: 'active',
}

describe('toServiceToken', () => {
  it('brands the id and maps all fields', () => {
    const token = toServiceToken(dto)
    expect(token.id).toBe(5)
    expect(token.subject).toBe('service:clear')
    expect(token.label).toBe('NeNe Clear')
    expect(token.scopes).toEqual(['read:invoices', 'write:payments'])
    expect(token.created_by).toBe(7)
    expect(token.status).toBe('active')
    expect(token.revoked_at).toBeNull()
  })

  it('normalises nullable created_by and revoked_at', () => {
    const token = toServiceToken({
      ...dto,
      created_by: null,
      revoked_at: '2026-06-10 00:00:00',
      status: 'revoked',
    })
    expect(token.created_by).toBeNull()
    expect(token.revoked_at).toBe('2026-06-10 00:00:00')
    expect(token.status).toBe('revoked')
  })
})

describe('toIssuedServiceToken', () => {
  it('includes the one-time plaintext token', () => {
    const created: CreatedServiceTokenDto = { ...dto, token: 'signed.jwt.value' }
    const issued = toIssuedServiceToken(created)
    expect(issued.token).toBe('signed.jwt.value')
    expect(issued.label).toBe('NeNe Clear')
  })
})

describe('toServiceTokenPage', () => {
  it('maps items and pagination', () => {
    const page = toServiceTokenPage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(5)
    expect(page.total).toBe(1)
  })

  it('maps an empty page', () => {
    const page = toServiceTokenPage({ items: [], total: 0, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(0)
  })
})
