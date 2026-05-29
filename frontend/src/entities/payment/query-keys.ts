export const paymentKeys = {
  all: ['payments'] as const,
  forInvoice: (invoiceId: number) => [...paymentKeys.all, 'invoice', invoiceId] as const,
}
