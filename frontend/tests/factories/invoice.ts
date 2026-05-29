import type { components } from '@/shared/api/schema.gen'

type InvoiceDto = components['schemas']['Invoice']

/** Builds an invoice wire shape (DTO) for MSW responses. */
export function buildInvoiceDto(overrides: Partial<InvoiceDto> = {}): InvoiceDto {
  return {
    id: 1,
    organization_id: 1,
    client_id: 5,
    status: 'issued',
    is_qualified_invoice: true,
    invoice_number: 'INV-2026-001',
    subtotal_cents: 106000,
    tax_cents: 10480,
    total_cents: 116480,
    ...overrides,
  }
}
