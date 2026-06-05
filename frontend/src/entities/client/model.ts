import type { ClientId } from './ids'

/** UI read model for a client (取引先). Field names mirror the API (snake_case). */
export interface Client {
  id: ClientId
  name: string
  /** Reading/index (furigana or latin) for sorting and suggestions. */
  name_kana: string | null
  contact_name: string | null
  email: string | null
  billing_address: string | null
  registration_number: string | null
}

export interface ClientPage {
  items: Client[]
  total: number
  limit: number
  offset: number
}

/** Applied search for the admin client list. */
export interface ClientListFilters {
  q: string | null
}

export const EMPTY_CLIENT_FILTERS: ClientListFilters = { q: null }

export type ClientSortField = 'name' | 'contact' | 'email' | 'registration'

export interface ClientSort {
  field: ClientSortField | null
  order: 'asc' | 'desc'
}

export interface CreateClientInput {
  name: string
  name_kana: string | null
  contact_name: string | null
  email: string | null
  billing_address: string | null
  registration_number: string | null
}

export interface UpdateClientInput extends CreateClientInput {
  id: ClientId
}
