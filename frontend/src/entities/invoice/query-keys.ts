export interface InvoiceListParams {
  limit: number
  offset: number
}

/** Hierarchical, typed query keys — features never write key strings. */
export const invoiceKeys = {
  all: ['invoices'] as const,
  lists: () => [...invoiceKeys.all, 'list'] as const,
  list: (params: InvoiceListParams) => [...invoiceKeys.lists(), params] as const,
}
