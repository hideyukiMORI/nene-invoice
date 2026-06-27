import type { components } from '@/shared/api/schema.gen'

type RecurringInvoiceDto = components['schemas']['RecurringInvoice']
type RecurringInvoiceWithLinesDto = components['schemas']['RecurringInvoiceWithLines']

export function buildRecurringInvoiceDto(
  overrides: Partial<RecurringInvoiceDto> = {},
): RecurringInvoiceDto {
  return {
    id: 7,
    organization_id: 1,
    client_id: 5,
    client_name: '得意先ABC',
    name: '月次顧問料',
    frequency: 'monthly',
    subtotal_cents: 100000,
    tax_cents: 10000,
    total_cents: 110000,
    next_run_on: '2026-07-01',
    last_run_on: null,
    is_active: true,
    notes: null,
    ...overrides,
  }
}

export function buildRecurringInvoiceWithLinesDto(
  overrides: Partial<RecurringInvoiceWithLinesDto> = {},
): RecurringInvoiceWithLinesDto {
  return {
    ...buildRecurringInvoiceDto(overrides),
    line_items: [
      { description: 'Consulting', quantity: 1, unit_price_cents: 100000, tax_rate_bps: 1000 },
    ],
    ...overrides,
  }
}
