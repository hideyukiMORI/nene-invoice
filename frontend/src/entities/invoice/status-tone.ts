import type { BadgeTone } from '@/shared/ui'
import type { InvoiceStatus } from './enum'

/** Badge tone per invoice lifecycle status (案C status chips). */
export const invoiceStatusTone: Record<InvoiceStatus, BadgeTone> = {
  draft: 'neutral',
  issued: 'info',
  partially_paid: 'warn',
  paid: 'ok',
}
