import { cloneElement, isValidElement, type ReactNode } from 'react'
import { Stack } from '../primitives/Stack'

export interface FieldProps {
  /** Must match the control's `id` and be used for `aria-describedby` on error. */
  id: string
  label: string
  error?: string | undefined
  children: ReactNode
}

type AriaControlProps = {
  'aria-invalid'?: boolean
  'aria-describedby'?: string
}

/**
 * Label + control + optional error message, wired for accessibility.
 *
 * 型1 field error (Issue #258): when `error` is set the control is marked
 * `aria-invalid` (red border via the primitives) and linked to the message,
 * which renders as `.err-text` directly beneath the field.
 */
export function Field({ id, label, error, children }: FieldProps) {
  const errorId = `${id}-error`
  const control =
    error !== undefined && isValidElement<AriaControlProps>(children)
      ? cloneElement(children, {
          'aria-invalid': true,
          'aria-describedby': errorId,
        } satisfies AriaControlProps)
      : children

  return (
    <Stack gap="sm">
      <label htmlFor={id} className="text-body text-fg-muted">
        {label}
      </label>
      {control}
      {error !== undefined && (
        <p id={errorId} role="alert" className="err-text">
          {error}
        </p>
      )}
    </Stack>
  )
}
