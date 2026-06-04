import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { MonthlyBilled } from '@/entities/dashboard'
import { invoiceStatusTone } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { formatYen } from '@/shared/lib/format-money'
import { Badge, EmptyState, ErrorState, LinkButton, LoadingState, Stack } from '@/shared/ui'
import { useViewDashboard } from '../hooks/use-view-dashboard'

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

  // Same month-over-month, for invoices issued (billed) this month.
  const billedLast = state.billedLastMonthCents
  let billedMomText: string | undefined
  let billedMomDir: 'up' | 'down' | undefined
  if (billedLast > 0) {
    const pct = Math.round(((state.billedThisMonthCents - billedLast) / billedLast) * 100)
    billedMomText = t('admin.dashboard.momChange', {
      change: `${pct > 0 ? '+' : ''}${String(pct)}%`,
    })
    billedMomDir = pct >= 0 ? 'up' : 'down'
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
          <LinkButton to="/quotes/new" variant="ghost">
            {t('admin.dashboard.createQuote')}
          </LinkButton>
          <LinkButton to="/invoices/new">{t('admin.dashboard.createInvoice')}</LinkButton>
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
          label={t('admin.dashboard.billedThisMonth')}
          value={formatYen(state.billedThisMonthCents)}
          foot={billedMomText}
          footClass={billedMomDir}
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

      <div className="panel">
        <div className="panel-head">
          <h3>{t('admin.dashboard.billedTrend')}</h3>
        </div>
        <div className="panel-body">
          <IssuanceTrend months={state.monthlyBilled} />
        </div>
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
                      <span className="flag-overdue">{t('admin.invoices.status.overdue')}</span>
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

/** Cents → 万円 with up to one decimal (e.g. 1,208,000 → "120.8"). */
const toMan = (cents: number): string => {
  const v = cents / 10000
  return (Math.round(v * 10) / 10).toString()
}

/**
 * (B) Monthly issuance trend (design 04): confirmed bars with the peak
 * highlighted, the in-progress current month shown as a projected landing
 * (着地見込み) with the actual filled in, and an average reference line.
 */
function IssuanceTrend({ months }: { months: MonthlyBilled[] }) {
  const { t, locale } = useTranslation()
  if (months.length === 0) return null

  const lastIdx = months.length - 1
  const confirmed = months.slice(0, lastIdx)
  const current = months[lastIdx]

  // Project the in-progress month's landing from the month-to-date pace.
  const now = new Date()
  const dayOfMonth = now.getDate()
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
  const pace = Math.max(dayOfMonth / daysInMonth, 0.0001)
  const projected = Math.round((current?.billed_cents ?? 0) / pace)

  const peakValue = Math.max(0, ...confirmed.map((m) => m.billed_cents))
  const scale = Math.max(peakValue, projected, 1)
  const average =
    confirmed.length > 0
      ? Math.round(confirmed.reduce((sum, m) => sum + m.billed_cents, 0) / confirmed.length)
      : 0

  const monthNo = (ym: string): number => Number(ym.slice(5))
  const monthLabel = (ym: string, isNow: boolean): string => {
    const n = monthNo(ym)
    if (locale === 'ja') return isNow ? `${String(n)}月` : String(n)
    return new Date(`${ym}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short' })
  }

  return (
    <>
      <div className="iss-plot">
        {average > 0 && (
          <div className="iss-avg" style={{ bottom: `${String((average / scale) * 100)}%` }}>
            <span>{t('admin.dashboard.average', { amount: formatYen(average) })}</span>
          </div>
        )}
        {months.map((m, i) => {
          const isNow = i === lastIdx
          const barValue = isNow ? projected : m.billed_cents
          const isPeak = !isNow && m.billed_cents === peakValue && peakValue > 0
          return (
            <div key={m.month} className={cn('iss-col', isNow && 'is-now')}>
              <span className="iss-v">{toMan(m.billed_cents)}</span>
              <div
                className={cn('iss-bar', isPeak && 'peak', isNow && 'proj')}
                style={{ height: `${String((barValue / scale) * 100)}%` }}
                title={
                  isNow
                    ? t('admin.dashboard.billedProjected', {
                        actual: formatYen(m.billed_cents),
                        projected: formatYen(projected),
                      })
                    : formatYen(m.billed_cents)
                }
              >
                {isNow && (
                  <span
                    className="fill"
                    style={{
                      height: `${String((m.billed_cents / Math.max(projected, 1)) * 100)}%`,
                    }}
                  />
                )}
              </div>
            </div>
          )
        })}
      </div>
      <div className="iss-x">
        {months.map((m, i) => (
          <div key={m.month} className={cn('iss-xl', i === lastIdx && 'is-now')}>
            {monthLabel(m.month, i === lastIdx)}
          </div>
        ))}
      </div>
      <div className="iss-unit">{t('admin.dashboard.billedUnit')}</div>
    </>
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
