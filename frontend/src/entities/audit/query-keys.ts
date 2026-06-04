import type { AuditLogFilters } from './model'

export interface AuditLogListParams extends AuditLogFilters {
  limit: number
  offset: number
}

export const auditKeys = {
  all: ['audit-logs'] as const,
  lists: () => [...auditKeys.all, 'list'] as const,
  list: (params: AuditLogListParams) => [...auditKeys.lists(), params] as const,
}
