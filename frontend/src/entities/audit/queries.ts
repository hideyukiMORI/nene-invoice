import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { AuditLogListDto } from './api-types'
import { toAuditLogPage } from './mapper'
import type { AuditLogPage } from './model'
import { auditKeys, type AuditLogListParams } from './query-keys'

/** GET /admin/audit-logs — filtered list page, mapped to models before the cache. */
export function useAuditLogList(
  params: AuditLogListParams,
): UseQueryResult<AuditLogPage, AppError> {
  return useQuery<AuditLogPage, AppError>({
    queryKey: auditKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      if (params.entity_type !== null) search.set('entity_type', params.entity_type)
      if (params.action !== null) search.set('action', params.action)
      if (params.actor_user_id !== null) search.set('actor_user_id', String(params.actor_user_id))
      if (params.created_from !== null) search.set('created_from', params.created_from)
      if (params.created_to !== null) search.set('created_to', params.created_to)

      const dto = await apiClient.get<AuditLogListDto>(`/admin/audit-logs?${search.toString()}`)
      return toAuditLogPage(dto)
    },
  })
}
