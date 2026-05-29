/** Branded id — prevents passing a bare number where an InvoiceId is required. */
export type InvoiceId = number & { readonly __brand: 'InvoiceId' }

export function toInvoiceId(value: number): InvoiceId {
  return value as InvoiceId
}
