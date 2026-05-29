import { useInvoiceList, type Invoice } from '@/entities/invoice'

const PAGE = { limit: 20, offset: 0 }

/** Narrowed view-model for the invoice list — one explicit state at a time. */
export type ListInvoicesState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; invoices: Invoice[] }

export function useListInvoices(): ListInvoicesState {
  const query = useInvoiceList(PAGE)

  if (query.isPending) {
    return { kind: 'loading' }
  }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }
  if (query.data.items.length === 0) {
    return { kind: 'empty' }
  }
  return { kind: 'ready', invoices: query.data.items }
}
