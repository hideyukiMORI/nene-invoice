import type { components } from '@/shared/api/schema.gen'

/** Wire shapes (snake_case) straight from the OpenAPI contract. */
export type InvoiceDto = components['schemas']['Invoice']
export type LineItemDto = components['schemas']['LineItem']
export type InvoiceWithLinesDto = components['schemas']['InvoiceWithLines']
export type SendInvoiceEmailPreviewDto = components['schemas']['SendInvoiceEmailPreview']

/**
 * List envelope. The OpenAPI `InvoiceList` is the generic page envelope, so the
 * typed item shape is pinned here (api-types may hand-type wire shapes).
 */
export interface InvoiceListDto {
  items: InvoiceDto[]
  total: number
  limit: number
  offset: number
}
