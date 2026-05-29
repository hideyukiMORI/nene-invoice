import { useId } from 'react'
import { Button } from '../primitives/Button'
import { Stack } from '../primitives/Stack'
import { Text } from '../primitives/Text'

export interface ConfirmDialogProps {
  title: string
  message: string
  confirmLabel: string
  cancelLabel: string
  /** When true the confirm action is in flight (buttons disabled). */
  pending?: boolean
  /** Confirm uses the danger variant when the action is destructive. */
  destructive?: boolean
  onConfirm: () => void
  onCancel: () => void
}

/**
 * Modal confirmation for destructive or irreversible actions. Token-driven; no
 * domain logic. Backdrop click and Cancel both dismiss.
 */
export function ConfirmDialog({
  title,
  message,
  confirmLabel,
  cancelLabel,
  pending = false,
  destructive = true,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const titleId = useId()

  return (
    <div className="fixed inset-0 z-modal flex items-center justify-center bg-surface-overlay/70 px-inline-md">
      <button
        type="button"
        aria-label={cancelLabel}
        className="absolute inset-0 size-full cursor-default"
        onClick={onCancel}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="relative w-full max-w-md rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg shadow-md"
      >
        <Stack gap="md">
          <Text as="h2" id={titleId} variant="heading-sm">
            {title}
          </Text>
          <Text variant="muted">{message}</Text>
          <Stack direction="row" gap="sm" className="justify-end">
            <Button variant="ghost" size="sm" onClick={onCancel} disabled={pending}>
              {cancelLabel}
            </Button>
            <Button
              variant={destructive ? 'danger' : 'primary'}
              size="sm"
              onClick={onConfirm}
              disabled={pending}
            >
              {confirmLabel}
            </Button>
          </Stack>
        </Stack>
      </div>
    </div>
  )
}
