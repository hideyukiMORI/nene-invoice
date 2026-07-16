import { useTranslation } from '@/shared/i18n'
import {
  Badge,
  Button,
  ErrorState,
  InlineAlert,
  LoadingState,
  Stack,
  Text,
  type InlineAlertTone,
} from '@/shared/ui'
import type { GatewayConnectivityDetail } from '@/entities/gateway-settings'
import { useGatewaySettingsView } from '../model/use-gateway-settings'

const DETAIL_TONE: Record<GatewayConnectivityDetail, InlineAlertTone> = {
  connected: 'success',
  not_configured: 'error',
  invalid_credentials: 'error',
  unreachable: 'error',
}

const DETAIL_KEY = {
  connected: 'admin.settings.gateway.testConnected',
  not_configured: 'admin.settings.gateway.testNotConfigured',
  invalid_credentials: 'admin.settings.gateway.testInvalidCredentials',
  unreachable: 'admin.settings.gateway.testUnreachable',
} as const satisfies Record<GatewayConnectivityDetail, string>

/** PAY.JP gateway status + connectivity test. Secrets live in env; never shown. */
export function GatewaySettings() {
  const { t } = useTranslation()
  const state = useGatewaySettingsView()

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.settings.gateway.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.settings.gateway.loadError')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const { settings, onTest, isTesting, result, testFailed } = state

  const yesNo = (set: boolean): string =>
    set ? t('admin.settings.gateway.set') : t('admin.settings.gateway.notSet')

  return (
    <div className="card">
      <div className="section-title">{t('admin.settings.gateway.title')}</div>
      <Stack gap="md">
        <Text variant="muted">{t('admin.settings.gateway.description')}</Text>

        <Stack gap="sm">
          <div className="flex items-center gap-inline-sm">
            <Text>{t('admin.settings.gateway.status')}</Text>
            {settings.configured ? (
              <Badge tone="ok">{t('admin.settings.gateway.statusConfigured')}</Badge>
            ) : (
              <Badge tone="warn">{t('admin.settings.gateway.statusNotConfigured')}</Badge>
            )}
          </div>
          <Text variant="muted">
            {t('admin.settings.gateway.publicKey')}: {settings.publicKeyMasked ?? '—'}
          </Text>
          <Text variant="muted">
            {t('admin.settings.gateway.secret')}: {yesNo(settings.secretSet)}
          </Text>
          <Text variant="muted">
            {t('admin.settings.gateway.webhookToken')}: {yesNo(settings.webhookTokenSet)}
          </Text>
        </Stack>

        <div>
          <Button variant="ghost" onClick={onTest} disabled={isTesting || !settings.configured}>
            {isTesting ? t('admin.settings.gateway.testing') : t('admin.settings.gateway.test')}
          </Button>
        </div>

        {testFailed ? (
          <InlineAlert tone="error" message={t('admin.settings.gateway.testError')} />
        ) : result ? (
          <InlineAlert tone={DETAIL_TONE[result.detail]} message={t(DETAIL_KEY[result.detail])} />
        ) : null}
      </Stack>
    </div>
  )
}
