import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { DashboardSummaryDto } from './api-types'
import { toDashboardSummary } from './mapper'
import type { DashboardSummary } from './model'

const dashboardKeys = {
  summary: ['dashboard', 'summary'] as const,
}

export function useDashboard(): UseQueryResult<DashboardSummary, AppError> {
  return useQuery<DashboardSummary, AppError>({
    queryKey: dashboardKeys.summary,
    queryFn: async () => {
      const dto = await apiClient.get<DashboardSummaryDto>('/admin/dashboard')
      return toDashboardSummary(dto)
    },
  })
}
