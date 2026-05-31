import { Link } from 'react-router-dom'
import { useExportInvoicesCsv, useExportPaymentsCsv } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Button, EmptyState, ErrorState, LoadingState, Stack, Text } from '@/shared/ui'
import { useListInvoices } from '../hooks/use-list-invoices'

/** Invoice list screen. Renders exactly one of loading / error / empty / ready. */
export function ListInvoices() {
  const { t } = useTranslation()
  const state = useListInvoices()
  const exportInvoices = useExportInvoicesCsv()
  const exportPayments = useExportPaymentsCsv()

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.invoices.title')}
        </Text>
        <Stack direction="row" gap="sm">
          <Button
            variant="ghost"
            size="sm"
            onClick={exportInvoices.download}
            disabled={exportInvoices.isDownloading}
          >
            {exportInvoices.isDownloading
              ? t('admin.invoices.export.downloading')
              : t('admin.invoices.export.invoices')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={exportPayments.download}
            disabled={exportPayments.isDownloading}
          >
            {exportPayments.isDownloading
              ? t('admin.invoices.export.downloading')
              : t('admin.invoices.export.payments')}
          </Button>
          <Link to="/invoices/new" className="text-body text-accent">
            {t('admin.invoices.newButton')}
          </Link>
        </Stack>
      </div>

      {(exportInvoices.errorMessage !== null || exportPayments.errorMessage !== null) && (
        <Text variant="muted" role="alert">
          {exportInvoices.errorMessage ?? exportPayments.errorMessage}
        </Text>
      )}

      {state.kind === 'loading' && <LoadingState message={t('admin.invoices.loading')} />}

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
                {t('common.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('common.pagination.info', {
                  page: state.pagination.page,
                  total: state.pagination.totalPages,
                })}
              </Text>
              <Button onClick={state.pagination.nextPage} disabled={!state.pagination.hasNext}>
                {t('common.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}
    </Stack>
  )
}
