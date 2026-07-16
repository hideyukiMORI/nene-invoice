import { useInvoice, type InvoiceId, type InvoiceWithLines } from '@/entities/invoice'

/** Narrowed view-model for the invoice detail (a single resource → no empty state). */
export type ViewInvoiceState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'ready'; invoice: InvoiceWithLines }

export function useViewInvoice(id: InvoiceId): ViewInvoiceState {
  const query = useInvoice(id)

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
  return { kind: 'ready', invoice: query.data }
}
