import type { ReactNode } from 'react'
import { Stack } from '../primitives/Stack'

export interface FieldProps {
  /** Must match the control's `id` and be used for `aria-describedby` on error. */
  id: string
  label: string
  error?: string | undefined
  children: ReactNode
}

/** Label + control + optional error message, wired for accessibility. */
export function Field({ id, label, error, children }: FieldProps) {
  return (
    <Stack gap="sm">
      <label htmlFor={id} className="text-body text-fg-muted">
        {label}
      </label>
      {children}
      {error !== undefined && (
        <p id={`${id}-error`} role="alert" className="text-body text-danger">
          {error}
        </p>
      )}
    </Stack>
  )
}
