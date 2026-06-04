import type { InvoiceListFilters, InvoiceSort } from './model'

export interface InvoiceListParams {
  limit: number
  offset: number
  filters: InvoiceListFilters
  sort: InvoiceSort
}

/** Hierarchical, typed query keys — features never write key strings. */
export const invoiceKeys = {
  all: ['invoices'] as const,
  lists: () => [...invoiceKeys.all, 'list'] as const,
  list: (params: InvoiceListParams) => [...invoiceKeys.lists(), params] as const,
  details: () => [...invoiceKeys.all, 'detail'] as const,
  detail: (id: number) => [...invoiceKeys.details(), id] as const,
}
