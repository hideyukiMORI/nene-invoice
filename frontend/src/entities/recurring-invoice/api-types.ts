import type { components } from '@/shared/api/schema.gen'

/** Wire shapes (snake_case) straight from the OpenAPI contract. */
export type RecurringInvoiceDto = components['schemas']['RecurringInvoice']
export type RecurringInvoiceWithLinesDto = components['schemas']['RecurringInvoiceWithLines']
export type LineItemDto = components['schemas']['LineItem']

/**
 * List envelope. The OpenAPI `RecurringInvoiceList` is the generic page envelope,
 * so the typed item shape is pinned here (api-types may hand-type wire shapes).
 */
export interface RecurringInvoiceListDto {
  items: RecurringInvoiceDto[]
  total: number
  limit: number
  offset: number
}
