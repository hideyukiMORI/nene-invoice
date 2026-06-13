import { useGatewaySettings, useTestGatewayConnectivity } from '@/entities/gateway-settings'
import type { GatewayConnectivity, GatewaySettings } from '@/entities/gateway-settings'

export type GatewaySettingsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      settings: GatewaySettings
      onTest: () => void
      isTesting: boolean
      /** Last connectivity result, or null before any test. */
      result: GatewayConnectivity | null
      /** True when the test request itself failed (not a non-ok result). */
      testFailed: boolean
    }

export function useGatewaySettingsView(): GatewaySettingsState {
  const query = useGatewaySettings()
  const test = useTestGatewayConnectivity()

  if (query.isPending) return { kind: 'loading' }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  return {
    kind: 'ready',
    settings: query.data,
    onTest: () => {
      test.mutate()
    },
    isTesting: test.isPending,
    result: test.data ?? null,
    testFailed: test.isError,
  }
}
