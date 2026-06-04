import type { InvoiceDto, InvoiceListDto, InvoiceWithLinesDto, LineItemDto } from './api-types'
import { toInvoiceId } from './ids'
import type { Invoice, InvoicePage, InvoiceWithLines, LineItem } from './model'

/** Pure DTO → model. Brands the id; normalises optional nulls. */
export function toInvoice(dto: InvoiceDto): Invoice {
  return {
    id: toInvoiceId(dto.id),
    client_id: dto.client_id,
    client_name: dto.client_name ?? null,
    invoice_number: dto.invoice_number ?? null,
    status: dto.status,
    is_overdue: dto.is_overdue,
    is_qualified_invoice: dto.is_qualified_invoice,
    issued_at: dto.issued_at ?? null,
    due_at: dto.due_at ?? null,
    subtotal_cents: dto.subtotal_cents,
    tax_cents: dto.tax_cents,
    total_cents: dto.total_cents,
    outstanding_cents: dto.outstanding_cents ?? null,
  }
}

export function toInvoicePage(dto: InvoiceListDto): InvoicePage {
  return {
    items: dto.items.map(toInvoice),
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

export function toInvoiceWithLines(dto: InvoiceWithLinesDto): InvoiceWithLines {
  return {
    ...toInvoice(dto),
    line_items: (dto.line_items ?? []).map(toLineItem),
  }
}
