import type { ServiceTokenId } from './ids'

export type ServiceScope = 'read:invoices' | 'write:payments'
export type ServiceTokenStatus = 'active' | 'revoked'

/** UI read model for a service token. Field names mirror the API (snake_case). */
export interface ServiceToken {
  id: ServiceTokenId
  subject: string
  label: string
  scopes: ServiceScope[]
  created_by: number | null
  created_at: string
  expires_at: string
  revoked_at: string | null
  status: ServiceTokenStatus
}

/** A token plus its one-time plaintext value, returned only on issuance. */
export interface IssuedServiceToken extends ServiceToken {
  token: string
}

export interface ServiceTokenPage {
  items: ServiceToken[]
  total: number
  limit: number
  offset: number
}

export interface IssueServiceTokenInput {
  label: string
  scopes: ServiceScope[]
}
