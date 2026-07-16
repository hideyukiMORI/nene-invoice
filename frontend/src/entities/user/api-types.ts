import type { components } from '@/shared/api/schema.gen'

export type UserDto = components['schemas']['User']

export interface UserListDto {
  items: UserDto[]
  total: number
  limit: number
  offset: number
}
