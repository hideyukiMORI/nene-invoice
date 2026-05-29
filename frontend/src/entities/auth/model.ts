import type { Role } from './enum'

export interface Credentials {
  email: string
  password: string
}

export interface CurrentUser {
  id: number
  email: string
  role: Role
  organization_id: number | null
}
