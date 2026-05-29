import type { components } from '@/shared/api/schema.gen'

export type ClientDto = components['schemas']['Client']

export interface ClientListDto {
  items: ClientDto[]
  total: number
  limit: number
  offset: number
}
