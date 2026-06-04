/** UI read model for one audit-trail entry. Field names mirror the API (snake_case). */
export interface AuditLog {
  id: number
  actor_user_id: number | null
  organization_id: number | null
  action: string
  entity_type: string
  entity_id: number | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  created_at: string | null
}

export interface AuditLogPage {
  items: AuditLog[]
  total: number
  limit: number
  offset: number
}

/** Read-side filters mirroring the GET /admin/audit-logs query parameters. */
export interface AuditLogFilters {
  entity_type: string | null
  action: string | null
  actor_user_id: number | null
  created_from: string | null
  created_to: string | null
}

export const EMPTY_AUDIT_LOG_FILTERS: AuditLogFilters = {
  entity_type: null,
  action: null,
  actor_user_id: null,
  created_from: null,
  created_to: null,
}
