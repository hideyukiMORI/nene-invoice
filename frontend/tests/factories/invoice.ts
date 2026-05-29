import type { components } from '@/shared/api/schema.gen'

type InvoiceDto = components['schemas']['Invoice']
type InvoiceWithLinesDto = components['schemas']['InvoiceWithLines']

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

/** Builds an invoice-with-line-items wire shape for the detail endpoint. */
export function buildInvoiceWithLinesDto(
  overrides: Partial<InvoiceWithLinesDto> = {},
): InvoiceWithLinesDto {
  return {
    ...buildInvoiceDto(),
    line_items: [
      {
        id: 1,
        description: 'コンサル料',
        quantity: 1,
        unit_price_cents: 100000,
        tax_rate_bps: 1000,
        sort_order: 0,
        line_subtotal_cents: 100000,
      },
      {
        id: 2,
        description: '書籍',
        quantity: 3,
        unit_price_cents: 2000,
        tax_rate_bps: 800,
        sort_order: 1,
        line_subtotal_cents: 6000,
      },
    ],
    ...overrides,
  }
}
