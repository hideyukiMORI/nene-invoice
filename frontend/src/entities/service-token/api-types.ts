import type { components } from '@/shared/api/schema.gen'

export type ServiceTokenDto = components['schemas']['ServiceToken']
export type CreatedServiceTokenDto = components['schemas']['CreatedServiceToken']

export interface ServiceTokenListDto {
  items: ServiceTokenDto[]
  total: number
  limit: number
  offset: number
}
