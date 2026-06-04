import type { components } from '@/shared/api/schema.gen'

export type AuditLogDto = components['schemas']['AuditLog']

export interface AuditLogListDto {
  items: AuditLogDto[]
  total: number
  limit: number
  offset: number
}
