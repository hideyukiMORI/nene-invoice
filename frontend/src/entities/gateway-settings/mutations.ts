import { useMutation, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { GatewayConnectivityDto } from './api-types'
import { toGatewayConnectivity } from './mapper'
import type { GatewayConnectivity } from './model'

/** POST /admin/gateway-settings/test — live connectivity check (always 200). */
export function useTestGatewayConnectivity(): UseMutationResult<
  GatewayConnectivity,
  AppError,
  void
> {
  return useMutation<GatewayConnectivity, AppError>({
    mutationFn: async () => {
      const dto = await apiClient.post<GatewayConnectivityDto>('/admin/gateway-settings/test')
      return toGatewayConnectivity(dto)
    },
  })
}
