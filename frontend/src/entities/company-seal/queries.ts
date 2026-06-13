import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CompanySeal, CompanySealDto } from './model'
import { companySealKeys } from './query-keys'

/** GET /admin/company-settings/seal. Returns the seal image (or has_seal=false). */
export function useCompanySeal(): UseQueryResult<CompanySeal, AppError> {
  return useQuery<CompanySeal, AppError>({
    queryKey: companySealKeys.detail(),
    queryFn: async () => {
      const dto = await apiClient.get<CompanySealDto>('/admin/company-settings/seal')
      return { has_seal: dto.has_seal, image_base64: dto.image_base64 ?? null }
    },
  })
}
