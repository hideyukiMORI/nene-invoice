import { useRecurringInvoiceList, type RecurringInvoice } from '@/entities/recurring-invoice'

const PAGE = { limit: 100, offset: 0 }

/** Narrowed view-model for the recurring-invoice list — one explicit state at a time. */
export type ListRecurringInvoicesState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; recurringInvoices: RecurringInvoice[] }

export interface ListRecurringInvoicesView {
  /** Total number of matching records. */
  total: number
  state: ListRecurringInvoicesState
}

export function useListRecurringInvoices(): ListRecurringInvoicesView {
  const query = useRecurringInvoiceList(PAGE)

  let state: ListRecurringInvoicesState
  if (query.isPending) {
    state = { kind: 'loading' }
  } else if (query.isError) {
    state = {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  } else if (query.data.items.length === 0) {
    state = { kind: 'empty' }
  } else {
    state = { kind: 'ready', recurringInvoices: query.data.items }
  }

  const total = query.data?.total ?? 0

  return { total, state }
}
