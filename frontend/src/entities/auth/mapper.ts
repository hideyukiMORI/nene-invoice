import type { CurrentUserDto } from './api-types'
import type { CurrentUser } from './model'

export function toCurrentUser(dto: CurrentUserDto): CurrentUser {
  return {
    id: dto.id,
    email: dto.email,
    role: dto.role,
    organization_id: dto.organization_id ?? null,
  }
}
