import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { EmptyState, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useListInvoices } from '../hooks/use-list-invoices'

/** Invoice list screen. Renders exactly one of loading / error / empty / ready. */
export function ListInvoices() {
  const { t } = useTranslation()
  const state = useListInvoices()

  return (
    <Stack gap="md">
      <Text as="h1" variant="heading-md">
        {t('admin.invoices.title')}
      </Text>

      {state.kind === 'loading' && (
        <Stack direction="row" gap="sm">
          <Spinner label={t('admin.invoices.loading')} />
          <Text variant="muted">{t('admin.invoices.loading')}</Text>
        </Stack>
      )}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.invoices.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.invoices.empty')} />}

      {state.kind === 'ready' && (
        <table className="w-full border-collapse text-body">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.invoices.col.number')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.invoices.col.status')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.invoices.col.client')}
              </th>
              <th className="py-stack-sm text-right font-medium">
                {t('admin.invoices.col.total')}
              </th>
            </tr>
          </thead>
          <tbody>
            {state.invoices.map((invoice) => (
              <tr key={invoice.id} className="border-b border-border">
                <td className="py-stack-sm pr-inline-md">{invoice.invoice_number ?? '—'}</td>
                <td className="py-stack-sm pr-inline-md">
                  {t(`admin.invoices.status.${invoice.status}`)}
                </td>
                <td className="py-stack-sm pr-inline-md">{invoice.client_id}</td>
                <td className="py-stack-sm text-right">{formatYen(invoice.total_cents)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Stack>
  )
}
