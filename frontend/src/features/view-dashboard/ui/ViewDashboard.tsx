import { Link } from 'react-router-dom'
import { invoiceStatusTone } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { formatYen } from '@/shared/lib/format-money'
import { Badge, EmptyState, ErrorState, LoadingState, Stack, Text } from '@/shared/ui'
import { useViewDashboard } from '../hooks/use-view-dashboard'

/** Admin dashboard: summary cards + recent unpaid invoice list. */
export function ViewDashboard() {
  const { t } = useTranslation()
  const state = useViewDashboard()

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.dashboard.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.dashboard.error')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  return (
    <Stack gap="lg">
      <Text as="h1" variant="heading-md">
        {t('admin.dashboard.title')}
      </Text>

      <div className="grid grid-cols-2 gap-inline-md sm:grid-cols-3">
        <SummaryCard
          label={t('admin.dashboard.unpaid')}
          value={String(state.unpaidCount)}
          sub={formatYen(state.outstandingTotalCents)}
        />
        <SummaryCard
          label={t('admin.dashboard.overdue')}
          value={String(state.overdueCount)}
          highlight={state.overdueCount > 0}
        />
        <SummaryCard
          label={t('admin.dashboard.outstanding')}
          value={formatYen(state.outstandingTotalCents)}
        />
      </div>

      <Stack gap="sm">
        <Text variant="heading-sm">{t('admin.dashboard.recentUnpaid')}</Text>

        {state.recentUnpaid.length === 0 ? (
          <EmptyState message={t('admin.dashboard.noUnpaid')} />
        ) : (
          <table className="data-table">
            <tbody>
              {state.recentUnpaid.map((invoice) => (
                <tr key={invoice.id}>
                  <td>
                    <Link to={`/invoices/${String(invoice.id)}`} className="num text-accent">
                      {invoice.invoice_number ?? '—'}
                    </Link>
                  </td>
                  <td>
                    <span className="flex items-center gap-inline-xs">
                      <Badge tone={invoiceStatusTone[invoice.status]}>
                        {t(`admin.invoices.status.${invoice.status}`)}
                      </Badge>
                      {invoice.is_overdue && (
                        <Badge tone="danger">{t('admin.invoices.status.overdue')}</Badge>
                      )}
                    </span>
                  </td>
                  <td className="tr num">
                    {invoice.due_at !== null ? invoice.due_at.slice(0, 10) : '—'}
                  </td>
                  <td className="tr num">
                    {invoice.outstanding_cents !== null
                      ? formatYen(invoice.outstanding_cents)
                      : formatYen(invoice.total_cents)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <div>
          <Link to="/invoices" className="text-body text-accent">
            {t('admin.invoices.title')} →
          </Link>
        </div>
      </Stack>
    </Stack>
  )
}

function SummaryCard({
  label,
  value,
  sub,
  highlight = false,
}: {
  label: string
  value: string
  sub?: string
  highlight?: boolean
}) {
  return (
    <div className="border border-border bg-surface-raised p-inline-md">
      <Text variant="muted" className="text-caption">
        {label}
      </Text>
      <Text as="p" variant="heading-md" className={cn('num', highlight && 'text-danger')}>
        {value}
      </Text>
      {sub !== undefined && (
        <Text variant="muted" className="num">
          {sub}
        </Text>
      )}
    </div>
  )
}
