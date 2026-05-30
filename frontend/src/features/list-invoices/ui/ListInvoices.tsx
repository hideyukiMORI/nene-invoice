import { Link } from 'react-router-dom'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Button, EmptyState, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useListInvoices } from '../hooks/use-list-invoices'

/** Invoice list screen. Renders exactly one of loading / error / empty / ready. */
export function ListInvoices() {
  const { t } = useTranslation()
  const state = useListInvoices()

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.invoices.title')}
        </Text>
        <Link to="/invoices/new" className="text-body text-accent">
          {t('admin.invoices.newButton')}
        </Link>
      </div>

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
        <>
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
                <th className="py-stack-sm pr-inline-md text-right font-medium">
                  {t('admin.invoices.col.total')}
                </th>
                <th className="py-stack-sm text-right font-medium">
                  {t('admin.invoices.col.outstanding')}
                </th>
              </tr>
            </thead>
            <tbody>
              {state.invoices.map((invoice) => (
                <tr key={invoice.id} className="border-b border-border">
                  <td className="py-stack-sm pr-inline-md">
                    <Link to={`/invoices/${String(invoice.id)}`} className="text-accent">
                      {invoice.invoice_number ?? '—'}
                    </Link>
                  </td>
                  <td className="py-stack-sm pr-inline-md">
                    <span>{t(`admin.invoices.status.${invoice.status}`)}</span>
                    {invoice.is_overdue && (
                      <span className="ml-inline-sm text-error text-caption font-medium">
                        {t('admin.invoices.status.overdue')}
                      </span>
                    )}
                  </td>
                  <td className="py-stack-sm pr-inline-md">{invoice.client_id}</td>
                  <td className="py-stack-sm pr-inline-md text-right">
                    {formatYen(invoice.total_cents)}
                  </td>
                  <td className="py-stack-sm text-right">
                    {invoice.outstanding_cents === null
                      ? '—'
                      : formatYen(invoice.outstanding_cents)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {state.pagination.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button onClick={state.pagination.prevPage} disabled={!state.pagination.hasPrev}>
                {t('admin.invoices.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('admin.invoices.pagination.info', {
                  page: state.pagination.page,
                  total: state.pagination.totalPages,
                })}
              </Text>
              <Button onClick={state.pagination.nextPage} disabled={!state.pagination.hasNext}>
                {t('admin.invoices.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}
    </Stack>
  )
}
