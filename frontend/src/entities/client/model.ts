import type { ClientId } from './ids'

/** UI read model for a client (取引先). Field names mirror the API (snake_case). */
export interface Client {
  id: ClientId
  name: string
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

export interface CreateClientInput {
  name: string
  contact_name: string | null
  email: string | null
  billing_address: string | null
  registration_number: string | null
}

export interface UpdateClientInput extends CreateClientInput {
  id: ClientId
}
