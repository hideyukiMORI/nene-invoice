import type { Toast } from './model'

/**
 * 型3 toast stack — fixed bottom-right, auto-dismissed by the provider.
 * Success uses role="status" (announced gently); errors use role="alert".
 * Meaning is carried by icon + text, never colour alone.
 */
export interface ToastStackProps {
  toasts: readonly Toast[]
  onDismiss: (id: string) => void
  closeLabel: string
}

function OkIcon() {
  return (
    <svg
      viewBox="0 0 20 20"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="10" cy="10" r="8" />
      <path d="M6.4 10.2l2.4 2.4 4.8-5.2" />
    </svg>
  )
}

function ErrIcon() {
  return (
    <svg
      viewBox="0 0 20 20"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M10 2.2L18.5 17H1.5z" />
      <path d="M10 8v4" />
      <path d="M10 14.6v.1" />
    </svg>
  )
}

function CloseIcon() {
  return (
    <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true">
      <path d="M3 3l8 8M11 3l-8 8" />
    </svg>
  )
}

export function ToastStack({ toasts, onDismiss, closeLabel }: ToastStackProps) {
  if (toasts.length === 0) return null
  return (
    <div className="toast-stack">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={`toast ${toast.tone}`}
          role={toast.tone === 'err' ? 'alert' : 'status'}
        >
          <span className="t-ico">{toast.tone === 'ok' ? <OkIcon /> : <ErrIcon />}</span>
          <div className="t-body">
            <div className="t-title">{toast.title}</div>
            {toast.description !== undefined && <div className="t-text">{toast.description}</div>}
            {toast.action !== undefined && (
              <button type="button" className="t-link" onClick={toast.action.onClick}>
                {toast.action.label}
              </button>
            )}
          </div>
          <button
            type="button"
            className="t-close"
            aria-label={closeLabel}
            onClick={() => {
              onDismiss(toast.id)
            }}
          >
            <CloseIcon />
          </button>
        </div>
      ))}
    </div>
  )
}
