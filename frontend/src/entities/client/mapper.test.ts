import { describe, expect, it } from 'vitest'
import type { ClientDto } from './api-types'
import { toClient, toClientPage } from './mapper'

const dto: ClientDto = {
  id: 5,
  organization_id: 1,
  name: '得意先ABC',
  contact_name: '山田',
  registration_number: 'T9876543210123',
}

describe('toClient', () => {
  it('brands the id and normalises optional fields', () => {
    const client = toClient(dto)
    expect(client.id).toBe(5)
    expect(client.name).toBe('得意先ABC')
    expect(client.contact_name).toBe('山田')
    expect(client.email).toBeNull()
  })
})

describe('toClientPage', () => {
  it('maps items and pagination', () => {
    const page = toClientPage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(5)
    expect(page.total).toBe(1)
  })
})
