import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { GatewaySettingsDto } from './api-types'
import { toGatewaySettings } from './mapper'
import type { GatewaySettings } from './model'
import { gatewaySettingsKeys } from './query-keys'

/** GET /admin/gateway-settings — PAY.JP configuration status (no secrets). */
export function useGatewaySettings(): UseQueryResult<GatewaySettings, AppError> {
  return useQuery<GatewaySettings, AppError>({
    queryKey: gatewaySettingsKeys.detail(),
    queryFn: async () => {
      const dto = await apiClient.get<GatewaySettingsDto>('/admin/gateway-settings')
      return toGatewaySettings(dto)
    },
  })
}
