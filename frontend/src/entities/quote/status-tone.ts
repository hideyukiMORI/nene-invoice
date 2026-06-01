import type { BadgeTone } from '@/shared/ui'
import type { QuoteStatus } from './model'

/** Badge tone per quote lifecycle status (案C status chips). */
export const quoteStatusTone: Record<QuoteStatus, BadgeTone> = {
  draft: 'neutral',
  sent: 'info',
  accepted: 'ok',
  rejected: 'danger',
  expired: 'warn',
}
