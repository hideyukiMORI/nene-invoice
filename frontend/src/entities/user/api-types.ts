import type { components } from '@/shared/api/schema.gen'

export type UserDto = components['schemas']['User']
export type CreateUserRequestDto = components['schemas']['CreateUserRequest']
export type UpdateUserRequestDto = components['schemas']['UpdateUserRequest']

export interface UserListDto {
  items: UserDto[]
  total: number
  limit: number
  offset: number
}
