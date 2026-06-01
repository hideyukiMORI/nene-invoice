import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { invoiceStatusTone } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Badge, EmptyState, ErrorState, LoadingState, Stack } from '@/shared/ui'
import { useViewDashboard } from '../hooks/use-view-dashboard'

const BTN_GHOST =
  'inline-flex items-center px-inline-md py-stack-xs text-body font-medium border border-border-strong bg-surface-raised text-fg transition-colors hover:bg-surface-overlay'
const BTN_PRIMARY =
  'inline-flex items-center px-inline-md py-stack-xs text-body font-medium bg-accent text-fg-inverse transition-colors hover:bg-accent-hover'

/** Admin dashboard: page head + summary stat cards + recent unpaid + AR aging. */
export function ViewDashboard() {
  const { t, locale } = useTranslation()
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

  const asOf = new Date().toLocaleDateString(locale === 'ja' ? 'ja-JP' : 'en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })

  // Month-over-month change in received payments (only when there is a prior base).
  const last = state.receivedLastMonthCents
  let momText: string | undefined
  let momDir: 'up' | 'down' | undefined
  if (last > 0) {
    const pct = Math.round(((state.receivedThisMonthCents - last) / last) * 100)
    momText = t('admin.dashboard.momChange', { change: `${pct > 0 ? '+' : ''}${String(pct)}%` })
    momDir = pct >= 0 ? 'up' : 'down'
  }

  const aging = state.aging
  const agingTotal = aging.current + aging.overdue_1_30 + aging.overdue_31_plus
  const pct = (n: number): number => (agingTotal > 0 ? Math.round((n / agingTotal) * 100) : 0)

  return (
    <Stack gap="lg">
      <div className="page-head">
        <div>
          <h1 className="page-title">{t('admin.dashboard.title')}</h1>
          <p className="page-sub">{t('admin.dashboard.asOf', { date: asOf })}</p>
        </div>
        <div className="flex items-center gap-inline-sm">
          <Link to="/quotes/new" className={BTN_GHOST}>
            {t('admin.dashboard.createQuote')}
          </Link>
          <Link to="/invoices/new" className={BTN_PRIMARY}>
            {t('admin.dashboard.createInvoice')}
          </Link>
        </div>
      </div>

      <div className="stats">
        <StatCard
          label={t('admin.dashboard.unpaid')}
          value={String(state.unpaidCount)}
          tone="c-brand"
          foot={t('admin.dashboard.totalAmount', {
            amount: formatYen(state.outstandingTotalCents),
          })}
        />
        <StatCard
          label={t('admin.dashboard.overdue')}
          value={String(state.overdueCount)}
          tone="c-danger"
        />
        <StatCard
          label={t('admin.dashboard.receivedThisMonth')}
          value={formatYen(state.receivedThisMonthCents)}
          foot={momText}
          footClass={momDir}
        />
        <StatCard
          label={t('admin.dashboard.outstanding')}
          value={formatYen(state.outstandingTotalCents)}
          foot={t('admin.dashboard.invoiceCount', { count: state.unpaidCount })}
        />
      </div>

      <div className="dash-grid">
        <div className="panel">
          <div className="panel-head">
            <h3>{t('admin.dashboard.recentUnpaid')}</h3>
            <Link to="/invoices" className="btn-link">
              {t('admin.dashboard.viewAll')}
            </Link>
          </div>
          <div className="px-inline-lg">
            {state.recentUnpaid.length === 0 ? (
              <div className="py-stack-md">
                <EmptyState message={t('admin.dashboard.noUnpaid')} />
              </div>
            ) : (
              state.recentUnpaid.map((invoice) => (
                <div key={invoice.id} className="mini-row">
                  <div className="mini-left">
                    <Link
                      to={`/invoices/${String(invoice.id)}`}
                      className="num font-semibold text-accent"
                    >
                      {invoice.invoice_number ?? '—'}
                    </Link>
                    <Badge tone={invoiceStatusTone[invoice.status]}>
                      {t(`admin.invoices.status.${invoice.status}`)}
                    </Badge>
                    {invoice.is_overdue && (
                      <Badge tone="danger">{t('admin.invoices.status.overdue')}</Badge>
                    )}
                  </div>
                  <div className="mini-right">
                    <div className="mini-amt">
                      {formatYen(invoice.outstanding_cents ?? invoice.total_cents)}
                    </div>
                    <div className="text-caption text-fg-muted">
                      {invoice.due_at !== null
                        ? t('admin.dashboard.due', { date: invoice.due_at.slice(0, 10) })
                        : '—'}
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        <div className="panel">
          <div className="panel-head">
            <h3>{t('admin.dashboard.aging')}</h3>
          </div>
          <div className="panel-body">
            <div className="flex flex-col gap-stack-sm">
              <AgingRow
                label={t('admin.dashboard.agingCurrent')}
                cents={aging.current}
                pct={pct(aging.current)}
              />
              <AgingRow
                label={t('admin.dashboard.aging1to30')}
                cents={aging.overdue_1_30}
                pct={pct(aging.overdue_1_30)}
                over
              />
              <AgingRow
                label={t('admin.dashboard.aging31plus')}
                cents={aging.overdue_31_plus}
                pct={pct(aging.overdue_31_plus)}
                over
                danger
              />
            </div>
            <hr className="my-stack-md border-t border-border" />
            <div className="flex items-center justify-between">
              <span className="font-semibold">{t('admin.dashboard.collectionsTotal')}</span>
              <span className="num font-bold">{formatYen(agingTotal)}</span>
            </div>
          </div>
        </div>
      </div>
    </Stack>
  )
}

function StatCard({
  label,
  value,
  foot,
  tone,
  footClass,
}: {
  label: string
  value: string
  foot?: ReactNode
  tone?: 'c-brand' | 'c-danger' | 'c-warn'
  footClass?: 'up' | 'down'
}) {
  return (
    <div className="stat">
      <div className="stat-label">{label}</div>
      <div className={tone ? `stat-num ${tone}` : 'stat-num'}>{value}</div>
      {foot !== undefined && (
        <div className={footClass ? `stat-foot ${footClass}` : 'stat-foot'}>{foot}</div>
      )}
    </div>
  )
}

function AgingRow({
  label,
  cents,
  pct,
  over = false,
  danger = false,
}: {
  label: string
  cents: number
  pct: number
  over?: boolean
  danger?: boolean
}) {
  return (
    <div>
      <div className="flex items-center justify-between">
        <span className="text-caption text-fg-muted">{label}</span>
        <span
          className={
            danger ? 'num text-caption font-semibold text-danger' : 'num text-caption font-semibold'
          }
        >
          {formatYen(cents)}
        </span>
      </div>
      <div className={over ? 'pay-bar over mt-stack-xs' : 'pay-bar mt-stack-xs'}>
        <span style={{ width: `${String(pct)}%` }} />
      </div>
    </div>
  )
}
