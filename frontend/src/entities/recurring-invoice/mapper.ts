import type {
  LineItemDto,
  RecurringInvoiceDto,
  RecurringInvoiceListDto,
  RecurringInvoiceWithLinesDto,
} from './api-types'
import { toRecurringInvoiceId } from './ids'
import type {
  LineItem,
  RecurringInvoice,
  RecurringInvoicePage,
  RecurringInvoiceWithLines,
} from './model'

/** Pure DTO → model. Brands the id; normalises optional nulls. */
export function toRecurringInvoice(dto: RecurringInvoiceDto): RecurringInvoice {
  return {
    id: toRecurringInvoiceId(dto.id),
    client_id: dto.client_id,
    client_name: dto.client_name ?? null,
    name: dto.name,
    frequency: dto.frequency,
    subtotal_cents: dto.subtotal_cents,
    tax_cents: dto.tax_cents,
    total_cents: dto.total_cents,
    next_run_on: dto.next_run_on,
    last_run_on: dto.last_run_on ?? null,
    is_active: dto.is_active,
    notes: dto.notes ?? null,
  }
}

export function toRecurringInvoicePage(dto: RecurringInvoiceListDto): RecurringInvoicePage {
  return {
    items: dto.items.map(toRecurringInvoice),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}

function toLineItem(dto: LineItemDto): LineItem {
  const quantity = dto.quantity
  const unitPrice = dto.unit_price_cents
  return {
    description: dto.description,
    quantity,
    unit_price_cents: unitPrice,
    tax_rate_bps: dto.tax_rate_bps,
    line_subtotal_cents: dto.line_subtotal_cents ?? quantity * unitPrice,
  }
}

export function toRecurringInvoiceWithLines(
  dto: RecurringInvoiceWithLinesDto,
): RecurringInvoiceWithLines {
  return {
    ...toRecurringInvoice(dto),
    line_items: (dto.line_items ?? []).map(toLineItem),
  }
}
