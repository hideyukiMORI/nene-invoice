export interface RecurringInvoiceListParams {
  limit: number
  offset: number
}

/** Hierarchical, typed query keys — features never write key strings. */
export const recurringInvoiceKeys = {
  all: ['recurring-invoices'] as const,
  lists: () => [...recurringInvoiceKeys.all, 'list'] as const,
  list: (params: RecurringInvoiceListParams) => [...recurringInvoiceKeys.lists(), params] as const,
  details: () => [...recurringInvoiceKeys.all, 'detail'] as const,
  detail: (id: number) => [...recurringInvoiceKeys.details(), id] as const,
}
