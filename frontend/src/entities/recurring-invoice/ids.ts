/** Branded id — prevents passing a bare number where a RecurringInvoiceId is required. */
export type RecurringInvoiceId = number & { readonly __brand: 'RecurringInvoiceId' }

export function toRecurringInvoiceId(value: number): RecurringInvoiceId {
  return value as RecurringInvoiceId
}
