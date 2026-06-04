import type { AuditLogDto, AuditLogListDto } from './api-types'
import type { AuditLog, AuditLogPage } from './model'

function toRecord(value: unknown): Record<string, unknown> | null {
  return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : null
}

export function toAuditLog(dto: AuditLogDto): AuditLog {
  return {
    id: dto.id,
    actor_user_id: dto.actor_user_id ?? null,
    organization_id: dto.organization_id ?? null,
    action: dto.action,
    entity_type: dto.entity_type,
    entity_id: dto.entity_id ?? null,
    before: toRecord(dto.before),
    after: toRecord(dto.after),
    created_at: dto.created_at ?? null,
  }
}

export function toAuditLogPage(dto: AuditLogListDto): AuditLogPage {
  return {
    items: dto.items.map(toAuditLog),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
