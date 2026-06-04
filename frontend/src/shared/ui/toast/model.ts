/** Toast model — 型3 of the error-display system (Issue #258). */

export type ToastTone = 'ok' | 'err'

export interface ToastAction {
  label: string
  onClick: () => void
}

export interface ToastInput {
  tone: ToastTone
  title: string
  description?: string
  /** Optional inline action, e.g. "retry" on a transient comms error. */
  action?: ToastAction
  /** Auto-dismiss delay in ms. Defaults to 5000. */
  durationMs?: number
}

export interface Toast extends ToastInput {
  id: string
}
