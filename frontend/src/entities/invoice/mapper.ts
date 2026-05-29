import type { InvoiceDto, InvoiceListDto } from './api-types'
import { toInvoiceId } from './ids'
import type { Invoice, InvoicePage } from './model'

/** Pure DTO → model. Brands the id; normalises optional nulls. */
export function toInvoice(dto: InvoiceDto): Invoice {
  return {
    id: toInvoiceId(dto.id),
    invoice_number: dto.invoice_number ?? null,
    status: dto.status,
    client_id: dto.client_id,
    is_qualified_invoice: dto.is_qualified_invoice,
    issued_at: dto.issued_at ?? null,
    due_at: dto.due_at ?? null,
    subtotal_cents: dto.subtotal_cents,
    tax_cents: dto.tax_cents,
    total_cents: dto.total_cents,
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
