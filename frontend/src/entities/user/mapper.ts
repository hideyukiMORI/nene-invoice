import type { UserDto, UserListDto } from './api-types'
import { toUserId } from './ids'
import type { User, UserPage } from './model'

export function toUser(dto: UserDto): User {
  return {
    id: toUserId(dto.id),
    email: dto.email,
    role: dto.role,
    organization_id: dto.organization_id ?? null,
    status: dto.status ?? 'active',
    created_at: dto.created_at ?? null,
    updated_at: dto.updated_at ?? null,
  }
}

export function toUserPage(dto: UserListDto): UserPage {
  return {
    items: dto.items.map(toUser),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
