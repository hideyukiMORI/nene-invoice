/**
 * 型2 — action error (Issue #258 error-display system).
 *
 * Shown fixed directly below the action button the user pressed when an
 * operation was attempted but did not complete (e.g. email send failed, issue
 * rejected). Unlike a toast it never auto-dismisses: it carries the cause and a
 * "next step" so the user knows how to recover. Uses role="alert" so assistive
 * tech announces it immediately. Meaning is conveyed by icon + text, not colour
 * alone.
 */

export interface ActionErrorAction {
  label: string
  onClick: () => void
  /** Primary recovery action is "solid"; secondary navigations are "outline". */
  variant?: 'solid' | 'outline'
}

export interface ActionErrorProps {
  title: string
  description: string
  actions?: readonly ActionErrorAction[]
  /** When provided, renders a dismiss button (accessible label required). */
  onClose?: () => void
  closeLabel?: string
}

function WarningIcon() {
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

export function ActionError({
  title,
  description,
  actions,
  onClose,
  closeLabel,
}: ActionErrorProps) {
  return (
    <div className="alert-rich" role="alert">
      <span className="ar-ico">
        <WarningIcon />
      </span>
      <div className="ar-body">
        <div className="ar-title">{title}</div>
        <div className="ar-text">{description}</div>
        {actions !== undefined && actions.length > 0 && (
          <div className="ar-actions">
            {actions.map((action) => (
              <button
                key={action.label}
                type="button"
                className={`btn-err ${action.variant ?? 'outline'}`}
                onClick={action.onClick}
              >
                {action.label}
              </button>
            ))}
          </div>
        )}
      </div>
      {onClose !== undefined && (
        <button type="button" className="ar-close" aria-label={closeLabel} onClick={onClose}>
          <CloseIcon />
        </button>
      )}
    </div>
  )
}
