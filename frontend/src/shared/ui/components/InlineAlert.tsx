/**
 * Lightweight inline notice (Issue #258). The single-line counterpart of
 * {@link ActionError}: used for 型2 action errors whose cause is singular
 * (e.g. issue rejected, overpayment) and for inline success/info notices.
 *
 * An optional `recover` renders a "next step" link below the notice — the same
 * recovery affordance as 型2, without the full title/body card. Errors use
 * role="alert"; success/info use role="status". Meaning is icon + text.
 */

export type InlineAlertTone = 'error' | 'success' | 'info'

export interface InlineAlertRecover {
  label: string
  onClick: () => void
}

export interface InlineAlertProps {
  tone: InlineAlertTone
  message: string
  recover?: InlineAlertRecover
}

function ErrorIcon() {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M8 1.8L15 14.5H1z" />
      <path d="M8 6.4v3.4M8 11.6v.1" />
    </svg>
  )
}

function SuccessIcon() {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M3.5 8.5l3 3 6-6.5" />
    </svg>
  )
}

function InfoIcon() {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="8" cy="8" r="6.3" />
      <path d="M8 7.4v3.4M8 5.2v.1" />
    </svg>
  )
}

function ToneIcon({ tone }: { tone: InlineAlertTone }) {
  if (tone === 'success') return <SuccessIcon />
  if (tone === 'info') return <InfoIcon />
  return <ErrorIcon />
}

export function InlineAlert({ tone, message, recover }: InlineAlertProps) {
  return (
    <div>
      <div className={`alert ${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
        <ToneIcon tone={tone} />
        <span>{message}</span>
      </div>
      {recover !== undefined && (
        <button type="button" className="recover-link" onClick={recover.onClick}>
          {recover.label}
        </button>
      )}
    </div>
  )
}
