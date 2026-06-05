import type { ClientDto, ClientListDto } from './api-types'
import { toClientId } from './ids'
import type { Client, ClientPage } from './model'

export function toClient(dto: ClientDto): Client {
  return {
    id: toClientId(dto.id),
    name: dto.name,
    name_kana: dto.name_kana ?? null,
    contact_name: dto.contact_name ?? null,
    email: dto.email ?? null,
    billing_address: dto.billing_address ?? null,
    registration_number: dto.registration_number ?? null,
  }
}

export function toClientPage(dto: ClientListDto): ClientPage {
  return {
    items: dto.items.map(toClient),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
