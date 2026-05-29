import { useTranslation } from '@/shared/i18n'
import { EmptyState, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useListClients } from '../hooks/use-list-clients'

/** Client (取引先) list screen. Renders exactly one of loading / error / empty / ready. */
export function ListClients() {
  const { t } = useTranslation()
  const state = useListClients()

  return (
    <Stack gap="md">
      <Text as="h1" variant="heading-md">
        {t('admin.clients.title')}
      </Text>

      {state.kind === 'loading' && (
        <Stack direction="row" gap="sm">
          <Spinner label={t('admin.clients.loading')} />
          <Text variant="muted">{t('admin.clients.loading')}</Text>
        </Stack>
      )}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.clients.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.clients.empty')} />}

      {state.kind === 'ready' && (
        <table className="w-full border-collapse text-body">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.name')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.contact')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.email')}
              </th>
              <th className="py-stack-sm font-medium">{t('admin.clients.col.registration')}</th>
            </tr>
          </thead>
          <tbody>
            {state.clients.map((client) => (
              <tr key={client.id} className="border-b border-border">
                <td className="py-stack-sm pr-inline-md">{client.name}</td>
                <td className="py-stack-sm pr-inline-md">{client.contact_name ?? '—'}</td>
                <td className="py-stack-sm pr-inline-md">{client.email ?? '—'}</td>
                <td className="py-stack-sm">{client.registration_number ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Stack>
  )
}
