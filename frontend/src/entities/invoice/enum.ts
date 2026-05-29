export const INVOICE_STATUSES = ['draft', 'issued', 'partially_paid', 'paid'] as const

export type InvoiceStatus = (typeof INVOICE_STATUSES)[number]
