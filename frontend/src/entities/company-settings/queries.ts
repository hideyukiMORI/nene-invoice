import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CompanySettingsDto } from './api-types'
import { toCompanySettings } from './mapper'
import type { CompanySettings } from './model'
import { companySettingsKeys } from './query-keys'

/** GET /admin/company-settings. Returns null (not an error) when not yet set (404). */
export function useCompanySettings(): UseQueryResult<CompanySettings | null, AppError> {
  return useQuery<CompanySettings | null, AppError>({
    queryKey: companySettingsKeys.detail(),
    queryFn: async () => {
      try {
        const dto = await apiClient.get<CompanySettingsDto>('/admin/company-settings')
        return toCompanySettings(dto)
      } catch (err) {
        const appErr = err as AppError
        if (appErr.status === 404) return null
        throw err
      }
    },
  })
}
