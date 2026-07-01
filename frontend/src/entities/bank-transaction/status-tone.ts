import type { BadgeTone } from '@/shared/ui'
import type { BankTransactionStatus } from './enum'

/** Badge tone per staged-line status (案C status chips). */
export const bankTransactionStatusTone: Record<BankTransactionStatus, BadgeTone> = {
  unmatched: 'neutral',
  matched: 'info',
  posted: 'ok',
  ignored: 'warn',
}
