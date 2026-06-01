import { useDashboard, type DashboardSummary, type ReceivableAging } from '@/entities/dashboard'
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
  }
}
