import type { UserId } from './ids'

export type UserRole = 'superadmin' | 'admin' | 'member' | 'viewer'
export type UserStatus = 'active' | 'invited'

/** UI read model for a user. Field names mirror the API (snake_case). */
export interface User {
  id: UserId
  email: string
  role: UserRole
  organization_id: number | null
  status: UserStatus
  created_at: string | null
  updated_at: string | null
}

export interface UserPage {
  items: User[]
  total: number
  limit: number
  offset: number
}

export interface CreateUserInput {
  email: string
  password: string
  role: UserRole
}

// PATCH /admin/users/{id} requires role and status (UpdateUserRequest); the
// edit form resends the user's current status so it stays unchanged (#622).
export interface UpdateUserInput {
  id: UserId
  email?: string
  password?: string
  role: UserRole
  status: UserStatus
}
