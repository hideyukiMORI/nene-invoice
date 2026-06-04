import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { DailyBilled, MonthlyBilled } from '@/entities/dashboard'
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

  // Issuance KPI metrics (design 04): projected landing + same-day / landing /
  // year-over-year comparisons from the daily-cumulative data.
  const issuanceNow = new Date()
  const todayDay = issuanceNow.getDate()
  const daysInMonth = new Date(issuanceNow.getFullYear(), issuanceNow.getMonth() + 1, 0).getDate()
  const projectedBilled =
    todayDay > 0
      ? Math.round(state.billedThisMonthCents / (todayDay / daysInMonth))
      : state.billedThisMonthCents
  const prevMonthSameDay =
    state.billedDailyPrevMonth.find((d) => d.day === todayDay)?.cumulative_cents ?? 0

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

      <div className="iss-grid3">
        <div className="iss-kpi card">
          <div className="k">{t('admin.dashboard.billedThisMonth')}</div>
          <div className="kdate">{t('admin.dashboard.asOfShort', { date: asOf })}</div>
          <div className="now">
            <span className="amt num">{formatYen(state.billedThisMonthCents)}</span>
            <span className="iss-prog">
              <span className="dot" />
              {t('admin.dashboard.inProgress')}
            </span>
          </div>
          <div className="kpi-list">
            <KpiRow label={t('admin.dashboard.landing')} value={formatYen(projectedBilled)} />
            <KpiRow
              label={t('admin.dashboard.vsLastMonthSameDay')}
              delta={pctDelta(state.billedThisMonthCents, prevMonthSameDay)}
            />
            <KpiRow
              label={t('admin.dashboard.vsLastMonthLanding')}
              delta={pctDelta(projectedBilled, state.billedLastMonthCents)}
            />
            <KpiRow
              label={t('admin.dashboard.yoy')}
              delta={pctDelta(state.billedThisMonthCents, state.billedPrevYearMonthCents)}
            />
          </div>
        </div>

        <div className="iss-sub card">
          <div className="iss-sub-h">
            {t('admin.dashboard.monthlyTrend')}
            <span className="sm">{t('admin.dashboard.last6Months')}</span>
          </div>
          <IssuanceTrend months={state.monthlyBilled} />
        </div>

        <div className="iss-sub card">
          <div className="iss-sub-h">
            {t('admin.dashboard.dailyTrend')}
            <span className="sm">{t('admin.dashboard.towardClose')}</span>
          </div>
          <DailyCumulativeView
            current={state.billedDailyCurrent}
            prevMonth={state.billedDailyPrevMonth}
          />
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

/**
 * (C) Daily cumulative pace toward the month-end close (design 04). Plots this
 * month's running total, the prior month for a same-day comparison (ghost), and
 * the projected landing from today's pace.
 */
function DailyCumulativeView({
  current,
  prevMonth,
}: {
  current: DailyBilled[]
  prevMonth: DailyBilled[]
}) {
  const { t, locale } = useTranslation()
  if (current.length === 0) {
    return <EmptyState message={t('admin.dashboard.noBilled')} />
  }

  const now = new Date()
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
  const todayDay = current[current.length - 1]?.day ?? now.getDate()
  const currentLast = current[current.length - 1]?.cumulative_cents ?? 0
  const projected = todayDay > 0 ? Math.round(currentLast / (todayDay / daysInMonth)) : currentLast
  const prevLast =
    prevMonth.length > 0 ? (prevMonth[prevMonth.length - 1]?.cumulative_cents ?? 0) : 0
  const ghostToday =
    prevMonth.find((d) => d.day === todayDay)?.cumulative_cents ??
    (prevMonth.length > 0 ? prevLast : 0)

  const niceMax = Math.max(
    Math.ceil(Math.max(currentLast, projected, prevLast) / 500000) * 500000,
    500000,
  )

  // viewBox 0 -12 520 208 — plot x 40..508, baseline y 170, top y 20.
  const x = (day: number): number =>
    daysInMonth > 1 ? 40 + ((day - 1) / (daysInMonth - 1)) * 468 : 40
  const y = (v: number): number => 170 - (v / niceMax) * 150
  const r2 = (n: number): string => (Math.round(n * 100) / 100).toString()
  const pts = (rows: { day: number; cumulative_cents: number }[]): string =>
    rows.map((d) => `${r2(x(Math.min(d.day, daysInMonth)))},${r2(y(d.cumulative_cents))}`).join(' ')

  // Fine label (one decimal 万) for the highlighted current/landing values.
  const man = (c: number): string =>
    locale === 'ja'
      ? `${(Math.round(c / 1000) / 10).toString()}万`
      : `¥${String(Math.round(c / 1000))}k`
  // Compact label for axis ticks — stays short for large amounts (fits the gutter).
  const manAxis = (c: number): string => {
    if (locale === 'ja') return `${String(Math.round(c / 10000))}万`
    return c >= 1_000_000
      ? `¥${(Math.round(c / 100000) / 10).toString()}M`
      : `¥${String(Math.round(c / 1000))}k`
  }
  const todayX = x(todayDay)
  const nowPts = pts(current)
  const areaPts = `${nowPts} ${r2(todayX)},170 ${r2(x(1))},170`

  // Sparse x-axis ticks: 1, quarter marks, and the close day.
  const ticks = [
    ...new Set([
      1,
      Math.round(daysInMonth / 4),
      Math.round(daysInMonth / 2),
      Math.round((daysInMonth * 3) / 4),
      daysInMonth,
    ]),
  ].filter((d) => d >= 1 && d <= daysInMonth)

  return (
    <>
      <svg
        className="iss-line"
        viewBox="0 -12 520 208"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        aria-label={t('admin.dashboard.billedTrend')}
      >
        {[0, niceMax / 2, niceMax].map((v) => (
          <g key={v}>
            <line className="lc-grid" x1="40" y1={r2(y(v))} x2="508" y2={r2(y(v))} />
            <text className="lc-tx axis" x="34" y={r2(y(v) + 3)} textAnchor="end">
              {v === 0 ? '0' : manAxis(v)}
            </text>
          </g>
        ))}
        <line className="lc-today" x1={r2(todayX)} y1="14" x2={r2(todayX)} y2="170" />
        <text className="lc-tx now-mark" x={r2(todayX)} y="10" textAnchor="middle">
          {t('admin.dashboard.dailyToday', {
            date: `${String(now.getMonth() + 1)}/${String(todayDay)}`,
          })}
        </text>

        <polygon className="lc-area" points={areaPts} />
        {prevMonth.length > 0 && <polyline className="lc-ghost" points={pts(prevMonth)} />}
        {prevMonth.length > 0 && (
          <circle className="lc-dot-ghost" cx={r2(todayX)} cy={r2(y(ghostToday))} r="3" />
        )}

        <polyline
          className="lc-proj"
          points={`${r2(todayX)},${r2(y(currentLast))} ${r2(x(daysInMonth))},${r2(y(projected))}`}
        />
        <circle className="lc-dot-end" cx={r2(x(daysInMonth))} cy={r2(y(projected))} r="4.5" />
        <text
          className="lc-tx proj"
          x={r2(x(daysInMonth) - 4)}
          y={r2(y(projected) - 9)}
          textAnchor="end"
        >
          {t('admin.dashboard.dailyLanding', { amount: manAxis(projected) })}
        </text>

        <polyline className="lc-now" points={nowPts} />
        <circle className="lc-dot-now" cx={r2(todayX)} cy={r2(y(currentLast))} r="4.5" />
        <text className="lc-tx now" x={r2(todayX - 6)} y={r2(y(currentLast) - 7)} textAnchor="end">
          {man(currentLast)}
        </text>

        {ticks.map((d) => (
          <text
            key={d}
            className="lc-tx axis"
            x={r2(x(d))}
            y="186"
            textAnchor={d === daysInMonth ? 'end' : 'middle'}
          >
            {d === daysInMonth ? t('admin.dashboard.dailyClose', { day: d }) : d}
          </text>
        ))}
      </svg>
      <div className="iss-legend2">
        <span className="now">
          <span className="lg-line" />
          {t('admin.dashboard.legendNow')}
        </span>
        <span className="proj">
          <span className="lg-line" />
          {t('admin.dashboard.legendProj')}
        </span>
        {prevMonth.length > 0 && (
          <span className="ghost">
            <span className="lg-line" />
            {t('admin.dashboard.legendGhost')}
          </span>
        )}
      </div>
    </>
  )
}

interface PctDelta {
  text: string
  dir: 'up' | 'down'
}

/** Percentage change vs a base, or null when there is no comparable base. */
function pctDelta(current: number, base: number): PctDelta | null {
  if (base <= 0) return null
  const pct = Math.round(((current - base) / base) * 1000) / 10
  const sign = pct > 0 ? '+' : pct < 0 ? '−' : ''
  return { text: `${sign}${String(Math.abs(pct))}%`, dir: pct >= 0 ? 'up' : 'down' }
}

/** A KPI comparison row in the issuance card: label + value or delta. */
function KpiRow({
  label,
  value,
  delta,
}: {
  label: string
  value?: string
  delta?: PctDelta | null
}) {
  return (
    <div className="kpi-row">
      <span className="kl">{label}</span>
      {value !== undefined ? (
        <span className="kv">{value}</span>
      ) : delta !== null && delta !== undefined ? (
        <span className={cn('kv', delta.dir)}>{delta.text}</span>
      ) : (
        <span className="kv">—</span>
      )}
    </div>
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
