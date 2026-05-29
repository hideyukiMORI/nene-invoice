import type { components } from '@/shared/api/schema.gen'

type ClientDto = components['schemas']['Client']

export function buildClientDto(overrides: Partial<ClientDto> = {}): ClientDto {
  return {
    id: 5,
    organization_id: 1,
    name: '得意先ABC',
    contact_name: '山田',
    email: null,
    registration_number: 'T9876543210123',
    ...overrides,
  }
}
