import {
  useDashboard,
  type DailyBilled,
  type DashboardSummary,
  type MonthlyBilled,
  type ReceivableAging,
} from '@/entities/dashboard'
import type { Invoice } from '@/entities/invoice'

export type ViewDashboardState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      unpaidCount: number
      overdueCount: number
      outstandingTotalCents: number
      recentUnpaid: Invoice[]
      receivedThisMonthCents: number
      receivedLastMonthCents: number
      aging: ReceivableAging
      billedThisMonthCents: number
      billedLastMonthCents: number
      monthlyBilled: MonthlyBilled[]
      billedPrevYearMonthCents: number
      billedDailyCurrent: DailyBilled[]
      billedDailyPrevMonth: DailyBilled[]
    }

export function useViewDashboard(): ViewDashboardState {
  const query = useDashboard()

  if (query.isPending) return { kind: 'loading' }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const data: DashboardSummary = query.data

  return {
    kind: 'ready',
    unpaidCount: data.unpaid_count,
    overdueCount: data.overdue_count,
    outstandingTotalCents: data.outstanding_total_cents,
    recentUnpaid: data.recent_unpaid,
    receivedThisMonthCents: data.received_this_month_cents,
    receivedLastMonthCents: data.received_last_month_cents,
    aging: data.aging,
    billedThisMonthCents: data.billed_this_month_cents,
    billedLastMonthCents: data.billed_last_month_cents,
    monthlyBilled: data.monthly_billed,
    billedPrevYearMonthCents: data.billed_prev_year_month_cents,
    billedDailyCurrent: data.billed_daily_current,
    billedDailyPrevMonth: data.billed_daily_prev_month,
  }
}
